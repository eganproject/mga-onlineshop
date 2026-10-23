<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentUpdate;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonInterface;

/**
 * Gateway untuk development lokal tanpa API key. Tagihan dibuat "pending"
 * dan bisa disimulasikan berhasil dari halaman pembayaran.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createPayment(Order $order, CarbonInterface $expiresAt): Payment
    {
        $attempt = $order->payments()->where('gateway', $this->name())->count() + 1;
        $externalId = 'FAKE-'.$order->code.($attempt > 1 ? '-'.$attempt : '');

        return $order->payments()->create([
            'gateway' => $this->name(),
            'external_id' => $externalId,
            'reference' => $externalId,
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'expires_at' => $expiresAt,
        ]);
    }

    public function parseNotification(array $payload): ?PaymentUpdate
    {
        return null;
    }

    public function fetchStatus(Payment $payment): ?PaymentUpdate
    {
        return null;
    }

    public function cancel(Payment $payment): void {}
}
