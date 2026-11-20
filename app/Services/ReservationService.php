<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Setting;
use App\Models\User;
use App\Support\OperatingHours;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;

/**
 * Aturan reservasi: membuat, menutup slot, membatalkan, dan mencatat kehadiran.
 */
class ReservationService
{
    public function __construct(private CourtLock $lock, private AuditLog $audit) {}

    /**
     * UC-02 (pelanggan) dan UC-07 (pegawai): membuat reservasi baru.
     *
     * @param  array{court_id: int, date: string, hour: int, duration: int, customer_name: string, customer_phone: string}  $input
     */
    public function create(array $input, BookingSource $source, ?User $actor = null): Booking
    {
        return $this->lock->run($input['court_id'], function (Court $court) use ($input, $source, $actor) {
            if (! $court->active) {
                throw RuleViolation::because('Lapangan ini sedang tidak dipakai.');
            }
            [$date, $start, $end] = $this->resolveTime($input);
            $this->ensureSlotFree($court, $start, $end);

            $setting = Setting::current();
            $total = $court->hourly_rate * $input['duration'];

            $booking = Booking::create([
                'code' => $this->newCode(),
                'court_id' => $court->id,
                'source' => $source,
                'customer_name' => $input['customer_name'],
                'customer_phone' => $input['customer_phone'],
                'play_date' => $date,
                'starts_at' => $start,
                'ends_at' => $end,
                'hourly_rate' => $court->hourly_rate,
                'total' => $total,
                'minimum_dp' => $setting->minimumDp($total),
                'status' => BookingStatus::Pending,
                // Slot ditahan selama hold_minutes, tetapi tidak boleh lewat jam main.
                'expires_at' => now()->addMinutes($setting->hold_minutes)->min($start),
                'created_by' => $actor?->id,
            ]);
            $this->audit->record($booking, $actor, 'created', ['source' => $source->value]);

            return $booking;
        });
    }

    /**
     * UC-13: pegawai menutup slot untuk perawatan atau acara.
     *
     * @param  array{court_id: int, date: string, hour: int, duration: int, reason: string}  $input
     */
    public function block(array $input, User $staff): Booking
    {
        return $this->lock->run($input['court_id'], function (Court $court) use ($input, $staff) {
            [$date, $start, $end] = $this->resolveTime($input);
            $this->ensureSlotFree($court, $start, $end);

            $booking = Booking::create([
                'code' => $this->newCode(),
                'court_id' => $court->id,
                'source' => BookingSource::Block,
                'customer_name' => 'Slot ditutup',
                'play_date' => $date,
                'starts_at' => $start,
                'ends_at' => $end,
                'hourly_rate' => 0,
                'total' => 0,
                'minimum_dp' => 0,
                'status' => BookingStatus::Confirmed,
                'note' => $input['reason'],
                'created_by' => $staff->id,
            ]);
            $this->audit->record($booking, $staff, 'blocked', ['reason' => $input['reason']]);

            return $booking;
        });
    }

    /**
     * UC-10: pegawai membatalkan reservasi, atau membuka slot yang ditutup.
     *
     * Asumsi kelompok: uang yang sudah diterima tidak dikembalikan (DP hangus)
     * dan tetap masuk omzet. Bukti yang belum diverifikasi otomatis ditolak.
     */
    public function cancel(Booking $booking, User $staff, ?string $reason = null): Booking
    {
        return $this->lock->run($booking->court_id, function () use ($booking, $staff, $reason) {
            $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if (! $current->status->isActive()) {
                throw RuleViolation::because('Reservasi ini sudah tidak aktif.');
            }
            if (! $current->isBlock() && ! $current->starts_at->isFuture()) {
                throw RuleViolation::because('Reservasi yang sudah mulai tidak bisa dibatalkan.');
            }

            $current->payments()->where('status', PaymentStatus::Pending)->update([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => 'Reservasi dibatalkan sebelum diverifikasi.',
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
            ]);
            $current->update(['status' => BookingStatus::Cancelled, 'note' => $reason ?? $current->note]);
            $this->audit->record($current, $staff, 'cancelled', [
                'reason' => $reason,
                'kept_amount' => $current->paidAmount(),
            ]);

            return $current;
        });
    }

    /**
     * Pelanggan membatalkan pesanannya sendiri selama belum mengirim bukti apa pun.
     * Tidak ada uang yang terlibat, jadi slot langsung dibuka lagi tanpa menunggu batas waktu tahan.
     */
    public function cancelUnpaidHold(Booking $booking): Booking
    {
        return $this->lock->run($booking->court_id, function () use ($booking) {
            $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($current->status !== BookingStatus::Pending || $current->payments()->exists()) {
                throw RuleViolation::because('Pesanan ini tidak bisa dibatalkan sendiri. Hubungi pegawai Lola Sport lewat WhatsApp.');
            }

            $current->update(['status' => BookingStatus::Cancelled, 'note' => 'Dibatalkan pelanggan sebelum membayar.']);
            $this->audit->record($current, null, 'cancelled_by_customer');

            return $current;
        });
    }

    /**
     * UC-12: pelanggan datang. Harus sudah lunas (KF-23).
     * Jam selesai tidak bergeser walaupun pelanggan telat (KF-24).
     */
    public function checkIn(Booking $booking, User $staff): Booking
    {
        return $this->lock->run($booking->court_id, function () use ($booking, $staff) {
            $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($current->status !== BookingStatus::Confirmed || $current->isBlock()) {
                throw RuleViolation::because('Hanya reservasi terkonfirmasi yang bisa ditandai hadir.');
            }
            if ($current->remainingAmount() > 0) {
                throw RuleViolation::because('Belum lunas. Catat pelunasan '.rupiah($current->remainingAmount()).' terlebih dahulu.');
            }
            $earliest = $current->starts_at->subMinutes((int) config('lola.check_in_early_minutes'));
            if (now()->lt($earliest) || now()->gte($current->ends_at)) {
                throw RuleViolation::because('Kehadiran hanya bisa dicatat mulai '.jam($earliest).' sampai '.jam($current->ends_at).'.');
            }

            $current->update(['status' => BookingStatus::CheckedIn, 'checked_in_at' => now()]);
            $this->audit->record($current, $staff, 'checked_in');

            return $current;
        });
    }

    /**
     * UC-12: pelanggan tidak datang setelah jam main dimulai.
     */
    public function markNoShow(Booking $booking, User $staff): Booking
    {
        return $this->lock->run($booking->court_id, function () use ($booking, $staff) {
            $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($current->status !== BookingStatus::Confirmed || $current->isBlock()) {
                throw RuleViolation::because('Hanya reservasi terkonfirmasi yang bisa ditandai tidak hadir.');
            }
            if ($current->starts_at->isFuture()) {
                throw RuleViolation::because('Tunggu sampai jam main dimulai.');
            }

            $current->update(['status' => BookingStatus::NoShow]);
            $this->audit->record($current, $staff, 'no_show');

            return $current;
        });
    }

    /**
     * Ubah input form menjadi tanggal operasional, jam mulai, dan jam selesai,
     * sambil memeriksa jam buka dan batas 30 hari.
     *
     * @param  array{date: string, hour: int, duration: int}  $input
     * @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable}
     */
    private function resolveTime(array $input): array
    {
        $date = CarbonImmutable::parse($input['date'])->startOfDay();
        $start = OperatingHours::slotStart($date, $input['hour']);
        $end = $start->addHours($input['duration']);

        if ($start->lte(now())) {
            throw RuleViolation::because('Jam mulai sudah lewat. Pilih jam yang akan datang.');
        }
        if ($date->gt(OperatingHours::lastBookableDate())) {
            throw RuleViolation::because('Reservasi paling jauh '.config('lola.max_days_ahead').' hari ke depan.');
        }
        if ($end->gt(OperatingHours::closesAt($date))) {
            throw RuleViolation::because('Durasi melewati jam tutup '.jam(OperatingHours::closesAt($date)).'.');
        }

        return [$date, $start, $end];
    }

    private function ensureSlotFree(Court $court, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $taken = Booking::query()
            ->where('court_id', $court->id)
            ->whereIn('status', BookingStatus::occupying())
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();

        if ($taken) {
            throw RuleViolation::because('Jadwal itu sudah terisi atau ditutup. Pilih jam lain.');
        }
    }

    /**
     * Kode booking pendek tanpa huruf yang mirip (O/0, I/1), contoh LS-7KQ3MX.
     */
    private function newCode(): string
    {
        $alphabet = 'ACDEFGHJKMNPQRTUVWXY34679';
        do {
            $code = 'LS-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Booking::query()->where('code', $code)->exists());

        return $code;
    }
}
