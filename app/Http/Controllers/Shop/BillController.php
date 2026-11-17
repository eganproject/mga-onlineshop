<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\BillingSettings;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Link tagihan yang dibagikan staf ke pembeli (WhatsApp, cetak). Tanpa
 * login: aksesnya lewat URL bertanda tangan (lihat Order::billUrl()).
 * Pembeli bisa memilih metode, lalu QR / nomor VA tampil di halaman ini.
 */
class BillController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function show(Order $order): View
    {
        $payment = null;
        $error = null;
        $pending = $order->status === OrderStatus::PendingPayment;

        if ($pending) {
            try {
                $payment = $this->payments->preparePayment($order);
            } catch (PaymentUnavailableException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                report($e);
                $error = 'Tagihan belum bisa dimuat. Silakan coba lagi beberapa saat lagi.';
            }
        }

        $canChoose = $pending && $this->payments->canCreatePayment($order);

        return view('shop.bill', [
            'order' => $order->load('items'),
            'payment' => $payment,
            'error' => $error,
            'pending' => $pending,
            'deadline' => $payment?->expires_at ?? $order->paymentDeadline(),
            'canChoose' => $canChoose,
            'methodOptions' => $canChoose ? $this->payments->methodOptions($order) : [],
            'urls' => $this->urls($order),
        ]);
    }

    public function choose(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['method' => ['required', Rule::enum(PaymentMethod::class)]], [
            'method.required' => 'Pilih metode pembayaran terlebih dahulu.',
        ]);
        $method = PaymentMethod::from($data['method']);
        $back = $order->billUrl();

        if (! in_array($method, BillingSettings::current()->enabledMethods(), true)) {
            return redirect($back)->withErrors(['method' => 'Metode pembayaran ini sedang tidak tersedia.']);
        }

        try {
            $this->payments->choose($order, $method);
        } catch (PaymentUnavailableException $e) {
            return redirect($back)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect($back)->with('error', $method->label().' sedang tidak bisa dipakai. Silakan coba lagi atau pilih metode lain.');
        }

        return redirect($back);
    }

    /** Status untuk dicek otomatis oleh halaman (ke gateway paling sering tiap 15 detik). */
    public function status(Order $order): JsonResponse
    {
        if ($order->status === OrderStatus::PendingPayment && Cache::add('payment:poll:'.$order->id, true, 15)) {
            try {
                $this->payments->syncPending($order);
                $order->refresh();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $done = $order->status !== OrderStatus::PendingPayment;

        return response()->json([
            'status' => $order->status === OrderStatus::Cancelled ? 'cancelled' : ($done ? 'paid' : 'pending'),
            'redirect' => $done ? $order->billUrl() : null,
        ]);
    }

    /** @return array{method: string, status: string} */
    private function urls(Order $order): array
    {
        $expires = $order->paymentDeadline()->copy()->addDays(30);

        return [
            'method' => URL::signedRoute('bill.method', $order, $expires),
            'status' => URL::signedRoute('bill.status', $order, $expires),
        ];
    }
}
