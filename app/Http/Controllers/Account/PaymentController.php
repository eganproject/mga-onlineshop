<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\BillingSettings;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Halaman pembayaran pesanan (Midtrans Snap / simulasi lokal). */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function show(Request $request, Order $order): View|RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if ($order->status !== OrderStatus::PendingPayment) {
            return redirect()->route('account.orders.show', $order)
                ->with('status', 'Pesanan ini tidak sedang menunggu pembayaran.');
        }

        $payment = null;
        $error = null;

        try {
            $payment = $this->payments->preparePayment($order);
        } catch (PaymentUnavailableException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $error = 'Link pembayaran belum bisa dibuat. Silakan coba lagi beberapa saat lagi.';
        }

        // Ada biaya transaksi yang dibebankan ke pembeli: pilih metode di sini dulu.
        $choosing = ! $payment && ! $error;

        return view('account.orders.payment', [
            'order' => $order->load('items'),
            'payment' => $payment,
            'error' => $error,
            'deadline' => $payment?->expires_at ?? $order->paymentDeadline(),
            'canRegenerate' => $this->payments->canCreatePayment($order),
            'choosing' => $choosing,
            'methodOptions' => $choosing || $this->payments->requiresMethodChoice() ? $this->payments->methodOptions($order) : [],
        ]);
    }

    /** Pembeli memilih metode bayar (dipakai bila ada biaya transaksi untuk pembeli). */
    public function choose(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $data = $request->validate(['method' => ['required', Rule::enum(PaymentMethod::class)]], [
            'method.required' => 'Pilih metode pembayaran terlebih dahulu.',
        ]);
        $method = PaymentMethod::from($data['method']);

        if (! in_array($method, BillingSettings::current()->enabledMethods(), true)) {
            return back()->withErrors(['method' => 'Metode pembayaran ini sedang tidak tersedia.']);
        }

        try {
            $this->payments->choose($order, $method);
        } catch (PaymentUnavailableException $e) {
            return redirect()->route('account.orders.show', $order)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Tagihan belum bisa dibuat. Silakan coba lagi beberapa saat lagi.');
        }

        if ($order->refresh()->status !== OrderStatus::PendingPayment) {
            return redirect()->route('account.orders.show', $order);
        }

        return redirect()->route('account.orders.payment', $order);
    }

    /** Pelanggan kembali dari halaman Midtrans (callback "finish"). */
    public function finish(Request $request, Order $order): RedirectResponse
    {
        return $this->check($request, $order);
    }

    /** Cek status langsung ke gateway, berguna bila webhook belum masuk. */
    public function check(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        try {
            $this->payments->syncPending($order);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('account.orders.show', $order)
                ->with('error', 'Status pembayaran belum bisa dicek. Silakan coba lagi nanti.');
        }

        $order->refresh();

        return redirect()->route('account.orders.show', $order)->with(...match ($order->status) {
            OrderStatus::PendingPayment => ['status', 'Pembayaran belum kami terima. Bila sudah membayar, status akan diperbarui otomatis dalam beberapa menit.'],
            OrderStatus::Cancelled => ['error', 'Pesanan ini sudah dibatalkan.'],
            default => ['success', 'Pembayaran diterima. Terima kasih! Pesanan segera kami proses.'],
        });
    }

    /** Buat link pembayaran baru (ganti metode, atau setelah gagal/kedaluwarsa). */
    public function regenerate(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        try {
            $payment = $this->payments->regenerate($order);
        } catch (PaymentUnavailableException $e) {
            return redirect()->route('account.orders.show', $order)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Link pembayaran baru belum bisa dibuat. Silakan coba lagi.');
        }

        if ($order->refresh()->status !== OrderStatus::PendingPayment) {
            return redirect()->route('account.orders.show', $order);
        }

        return redirect()->route('account.orders.payment', $order)
            ->with('success', $payment ? 'Link pembayaran baru sudah dibuat. Silakan pilih metode pembayaran.' : 'Silakan pilih metode pembayaran yang baru.');
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
