<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

/**
 * Gateway untuk development lokal. Pembayaran dibuat "pending" dan bisa
 * disimulasikan berhasil dari halaman detail pesanan.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createPayment(Order $order): Payment
    {
        return $order->payments()->create([
            'gateway' => $this->name(),
            'reference' => 'FAKE-'.$order->code,
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'expires_at' => now()->addHours(config('shop.payment.expiry_hours')),
        ]);
    }

    public function handleNotification(array $payload): ?Payment
    {
        return null;
    }
}
