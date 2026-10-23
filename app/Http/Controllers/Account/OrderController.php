<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        return view('account.orders.index', [
            'orders' => $request->user()->orders()
                ->with('items')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOwner($request, $order);

        return view('account.orders.show', [
            'order' => $order->load('items.product', 'latestPayment'),
        ]);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if ($order->status !== OrderStatus::PendingPayment) {
            return back()->with('error', 'Pesanan ini sudah tidak bisa dibatalkan.');
        }

        $orders->cancel($order);
        $order->latestPayment?->update(['status' => PaymentStatus::Failed]);

        return back()->with('success', 'Pesanan dibatalkan.');
    }

    /** Khusus development: tandai pembayaran driver "fake" sebagai berhasil. */
    public function simulatePayment(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $payment = $order->latestPayment;

        abort_if(app()->isProduction(), 404);
        abort_unless($payment?->gateway === 'fake' && $payment->status === PaymentStatus::Pending, 404);

        $orders->markPaid($payment, ['payment_type' => 'simulasi']);

        return back()->with('success', 'Pembayaran berhasil (simulasi).');
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
