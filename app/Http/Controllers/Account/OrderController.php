<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderService;
use App\Services\Payment\PaymentService;
use App\Services\Shipping\TrackingService;
use App\Support\InfiniteScroll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): View|Response
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        return InfiniteScroll::respond($request, view('account.orders.index', [
            'orders' => $request->user()->orders()
                ->with('items.product')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = $request->string('q')->trim()->value();
                    $q->where(fn ($q) => $q->where('code', 'like', "%{$term}%")
                        ->orWhereHas('items', fn ($i) => $i->where('product_name', 'like', "%{$term}%")));
                })
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'status' => $status,
        ]));
    }

    public function show(Request $request, Order $order, TrackingService $tracking): View
    {
        $this->authorizeOwner($request, $order);

        return view('account.orders.show', [
            'order' => $order->load('items.product', 'latestPayment', 'statusHistories'),
            'canTrack' => $tracking->canTrack($order),
        ]);
    }

    public function cancel(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if ($order->status !== OrderStatus::PendingPayment) {
            return back()->with('error', 'Pesanan ini sudah tidak bisa dibatalkan.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', Rule::in(config('shop.orders.cancel_reasons'))],
            'reason_detail' => ['nullable', 'string', 'max:200'],
        ]);

        $reason = collect([$data['reason'] ?? null, $data['reason_detail'] ?? null])->filter()->implode(': ');

        try {
            $cancelled = $payments->cancelByCustomer($order, 'Dibatalkan pelanggan'.($reason ? " — {$reason}" : '.'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Status pembayaran belum bisa dicek, pesanan belum dibatalkan. Silakan coba lagi.');
        }

        return redirect()->route('account.orders.show', $order)->with(...$cancelled
            ? ['success', 'Pesanan dibatalkan.']
            : ['error', 'Pesanan tidak dibatalkan karena pembayarannya sudah kami terima.']);
    }

    /** Pelanggan mengonfirmasi paket sudah diterima. */
    public function complete(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        try {
            $orders->complete($order, 'Dikonfirmasi diterima oleh pelanggan.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Terima kasih! Pesanan ditandai selesai.');
    }

    public function tracking(Request $request, Order $order, TrackingService $tracking): View
    {
        $this->authorizeOwner($request, $order);

        return view('partials.order-tracking', [
            'order' => $order,
            'result' => $tracking->track($order),
            'refreshUrl' => null,
        ]);
    }

    public function invoice(Request $request, Order $order): View
    {
        $this->authorizeOwner($request, $order);
        abort_unless($order->paid_at !== null, 404);

        return view('orders.invoice', ['order' => $order->load('items', 'latestPayment')]);
    }

    /** Khusus development: tandai pembayaran driver "fake" sebagai berhasil. */
    public function simulatePayment(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $payment = $order->latestPayment;

        abort_if(app()->isProduction(), 404);
        abort_unless($payment?->gateway === 'fake' && $payment->status === PaymentStatus::Pending, 404);

        $orders->markPaid($payment, ['payment_type' => 'simulasi']);

        return redirect()->route('account.orders.show', $order)->with('success', 'Pembayaran berhasil (simulasi).');
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
