<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Activity\ActivityLogger;
use App\Services\Orders\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Catat pembayaran yang diterima di luar Midtrans (tunai, transfer langsung
 * ke rekening toko, EDC, lainnya). Pesanan jadi lunas lewat OrderService,
 * jadi invoice, laporan & kirim ke gudang berjalan seperti pembayaran biasa.
 */
class OrderOfflinePaymentController extends Controller
{
    /** Bukti bayar disimpan privat (bukan disk public). */
    public const DISK = 'local';

    public function store(Request $request, Order $order, OrderService $orders, PaymentService $payments, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(Order::OFFLINE_METHODS))],
            'paid_at' => ['required', 'date', 'before_or_equal:now', 'after_or_equal:'.$order->created_at->copy()->startOfDay()->toDateTimeString()],
            'reference' => [Rule::requiredIf(fn () => $request->input('method') === 'bank_transfer'), 'nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:250'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'method.required' => 'Pilih cara pembayaran.',
            'paid_at.required' => 'Isi tanggal pembayaran.',
            'paid_at.before_or_equal' => 'Tanggal bayar tidak boleh di masa depan.',
            'paid_at.after_or_equal' => 'Tanggal bayar tidak boleh sebelum pesanan dibuat.',
            'reference.required' => 'Isi nomor referensi / rekening pengirim transfer.',
            'proof.mimes' => 'Bukti bayar harus berupa foto (JPG, PNG, WEBP) atau PDF.',
            'proof.max' => 'Ukuran bukti bayar maksimal 5 MB.',
        ]);

        if ($order->status !== OrderStatus::PendingPayment) {
            return back()->with('error', 'Pesanan ini sudah tidak menunggu pembayaran.');
        }

        // Jangan sampai dicatat dua kali padahal sudah lunas lewat Midtrans.
        try {
            $payments->syncPending($order);
        } catch (Throwable $e) {
            report($e);
        }

        if ($order->refresh()->status !== OrderStatus::PendingPayment) {
            return back()->with('info', 'Ternyata pembayaran lewat Midtrans sudah masuk. Tidak perlu dicatat lagi.');
        }

        $proof = $request->file('proof')?->store('payment-proofs/'.now()->format('Y/m'), self::DISK);

        try {
            $orders->recordOfflinePayment(
                $order,
                $data['method'],
                Carbon::parse($data['paid_at'], config('app.timezone')),
                filled($data['reference'] ?? null) ? trim($data['reference']) : null,
                $proof ?: null,
                $request->user()->id,
                filled($data['note'] ?? null) ? trim($data['note']) : null,
            );
        } catch (InvalidArgumentException $e) {
            if ($proof) {
                Storage::disk(self::DISK)->delete($proof);
            }

            return back()->with('error', $e->getMessage());
        }

        $label = Order::OFFLINE_METHODS[$data['method']];
        $activity->log('order.payment_recorded', "Mencatat pembayaran {$label} untuk pesanan {$order->code}", $order);

        return redirect()->route('admin.orders.show', $order)->with('success', "Pembayaran {$label} tercatat. Pesanan {$order->code} sekarang lunas.");
    }

    /** Tampilkan bukti bayar (privat, hanya staf yang bisa membuka pesanan). */
    public function proof(Order $order): StreamedResponse
    {
        abort_unless($order->payment_proof && Storage::disk(self::DISK)->exists($order->payment_proof), 404);

        return Storage::disk(self::DISK)->response($order->payment_proof, 'bukti-bayar-'.$order->code.'.'.pathinfo($order->payment_proof, PATHINFO_EXTENSION));
    }
}
