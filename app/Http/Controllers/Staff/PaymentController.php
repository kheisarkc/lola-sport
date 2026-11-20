<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pegawai memverifikasi bukti (UC-08) dan mencatat tunai (UC-09).
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /**
     * Semua bukti yang menunggu, dari yang paling lama dikirim.
     */
    public function index(): View
    {
        return view('staff.payments.index', [
            'pending' => Payment::query()
                ->with('booking.court', 'booking.payments')
                ->where('status', PaymentStatus::Pending)
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    /**
     * Bukti dibuka lewat controller ini, bukan link publik (KNF-03).
     */
    public function proof(Payment $payment): StreamedResponse
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        $this->payments->approve($payment, $this->staff($request));

        return redirect()->route('staff.payments.index')->with('success', 'Uang '.rupiah($payment->amount).' dari '.$payment->booking->customer_name.' diterima.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:3', 'max:200']],
            attributes: ['reason' => 'alasan penolakan'],
        );
        $this->payments->reject($payment, $this->staff($request), $data['reason']);

        return redirect()->route('staff.payments.index')->with('success', 'Bukti ditolak. Pelanggan bisa mengirim ulang.');
    }

    public function cash(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'cara' => ['nullable', 'in:tunai,qris'],
        ], attributes: ['amount' => 'nominal', 'cara' => 'cara bayar']);
        $method = ($data['cara'] ?? 'tunai') === 'qris' ? PaymentMethod::Qris : PaymentMethod::Cash;
        $this->payments->recordCash($booking, (int) $data['amount'], $this->staff($request), $method);

        return back()->with('success', $method->label().' '.rupiah((int) $data['amount']).' dicatat.');
    }

    private function staff(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
