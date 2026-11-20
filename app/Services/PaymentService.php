<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Support\RuleViolation;
use Illuminate\Http\UploadedFile;

/**
 * Aturan pembayaran:
 * - pembayaran pertama minimal DP, pembayaran berikutnya harus tepat sisa tagihan;
 * - total uang diterima tidak boleh melebihi tagihan;
 * - bukti QRIS baru dihitung setelah disetujui pegawai.
 */
class PaymentService
{
    public function __construct(private CourtLock $lock, private AuditLog $audit) {}

    /**
     * UC-03: pelanggan mengirim bukti pembayaran QRIS.
     */
    public function submitProof(Booking $booking, int $amount, UploadedFile $proof): Payment
    {
        return $this->lock->run($booking->court_id, function () use ($booking, $amount, $proof) {
            $current = $this->lockedBooking($booking);
            if ($current->hasPendingPayment()) {
                throw RuleViolation::because('Bukti sebelumnya masih menunggu verifikasi pegawai.');
            }
            $this->ensureAmountAllowed($current, $amount);

            // Disimpan di storage privat, hanya pegawai yang bisa membuka.
            $path = $proof->store('proofs', 'local');
            if ($path === false) {
                throw RuleViolation::because('Bukti gagal disimpan. Coba kirim ulang.');
            }

            $payment = $current->payments()->create([
                'amount' => $amount,
                'method' => PaymentMethod::Qris,
                'status' => PaymentStatus::Pending,
                'proof_path' => $path,
            ]);
            if ($current->status === BookingStatus::Pending) {
                $current->update(['status' => BookingStatus::Review]);
            }
            $this->audit->record($current, null, 'proof_submitted', ['payment_id' => $payment->id, 'amount' => $amount]);

            return $payment;
        });
    }

    /**
     * UC-09: pegawai mencatat uang yang diterima langsung di tempat, tunai atau QRIS.
     * QRIS di tempat tidak perlu bukti: pegawai melihat sendiri uangnya masuk saat itu.
     */
    public function recordCash(Booking $booking, int $amount, User $staff, PaymentMethod $method = PaymentMethod::Cash): Payment
    {
        return $this->lock->run($booking->court_id, function () use ($booking, $amount, $staff, $method) {
            $current = $this->lockedBooking($booking);
            if ($current->hasPendingPayment()) {
                throw RuleViolation::because('Verifikasi dulu bukti yang masih menunggu sebelum mencatat tunai.');
            }
            $this->ensureAmountAllowed($current, $amount);

            $payment = $current->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'status' => PaymentStatus::Approved,
                'recorded_by' => $staff->id,
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
            ]);
            $current->update(['status' => BookingStatus::Confirmed]);
            $this->audit->record($current, $staff, $method === PaymentMethod::Cash ? 'cash_recorded' : 'counter_qris_recorded', ['payment_id' => $payment->id, 'amount' => $amount]);

            return $payment;
        });
    }

    /**
     * UC-08: pegawai menyetujui bukti. Uangnya masuk omzet pada tanggal ini.
     */
    public function approve(Payment $payment, User $staff): Payment
    {
        return $this->lock->run($payment->booking->court_id, function () use ($payment, $staff) {
            $current = $this->lockedBooking($payment->booking);
            $pending = $this->lockedPendingPayment($payment);
            $this->ensureAmountAllowed($current, $pending->amount);

            $pending->update([
                'status' => PaymentStatus::Approved,
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
            ]);
            $current->update(['status' => BookingStatus::Confirmed]);
            $this->audit->record($current, $staff, 'payment_approved', ['payment_id' => $pending->id, 'amount' => $pending->amount]);

            return $pending;
        });
    }

    /**
     * UC-08 alur alternatif: bukti ditolak dengan alasan.
     * Bila DP belum pernah diterima, pesanan langsung batal dan jamnya dibuka lagi
     * (aturan web demo awal, dipertahankan atas keputusan tim 10 Nov 2026).
     */
    public function reject(Payment $payment, User $staff, string $reason): Payment
    {
        return $this->lock->run($payment->booking->court_id, function () use ($payment, $staff, $reason) {
            $current = $this->lockedBooking($payment->booking);
            $pending = $this->lockedPendingPayment($payment);

            $pending->update([
                'status' => PaymentStatus::Rejected,
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);
            if ($current->paidAmount() === 0) {
                $current->update([
                    'status' => BookingStatus::Cancelled,
                    'note' => 'Bukti DP ditolak: '.$reason,
                ]);
            }
            $this->audit->record($current, $staff, 'payment_rejected', ['payment_id' => $pending->id, 'reason' => $reason]);

            return $pending;
        });
    }

    private function lockedBooking(Booking $booking): Booking
    {
        $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
        if ($current->isBlock() || ! $current->status->isActive()) {
            throw RuleViolation::because('Reservasi ini tidak menerima pembayaran ('.$current->status->label().').');
        }

        return $current;
    }

    private function lockedPendingPayment(Payment $payment): Payment
    {
        $pending = Payment::query()->lockForUpdate()->findOrFail($payment->id);
        if ($pending->status !== PaymentStatus::Pending) {
            throw RuleViolation::because('Bukti ini sudah diputuskan sebelumnya.');
        }

        return $pending;
    }

    private function ensureAmountAllowed(Booking $booking, int $amount): void
    {
        $paid = $booking->paidAmount();
        $remaining = $booking->total - $paid;

        if ($remaining <= 0) {
            throw RuleViolation::because('Reservasi ini sudah lunas.');
        }
        if ($amount > $remaining) {
            throw RuleViolation::because('Nominal melebihi sisa tagihan '.rupiah($remaining).'.');
        }
        if ($paid === 0 && $amount < $booking->minimum_dp) {
            throw RuleViolation::because('Pembayaran pertama minimal DP '.rupiah($booking->minimum_dp).'.');
        }
        if ($paid > 0 && $amount !== $remaining) {
            throw RuleViolation::because('Pelunasan harus tepat sisa tagihan '.rupiah($remaining).'.');
        }
    }
}
