<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentCharge;
use App\Data\PaymentUpdate;
use App\Enums\PaymentMethod;
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

    public function createPayment(Order $order, CarbonInterface $expiresAt, ?PaymentCharge $charge = null): Payment
    {
        $charge ??= new PaymentCharge(amount: $order->total);

        $attempt = $order->payments()->where('gateway', $this->name())->count() + 1;
        $externalId = 'FAKE-'.$order->code.($attempt > 1 ? '-'.$attempt : '');

        return $order->payments()->create([
            'gateway' => $this->name(),
            'external_id' => $externalId,
            'reference' => $externalId,
            'status' => PaymentStatus::Pending,
            'amount' => $charge->amount,
            'fee' => $charge->fee,
            'gateway_fee' => $charge->gatewayFee,
            'method' => $charge->method?->value,
            'instructions' => $charge->method ? self::instructionsFor($charge->method, $order) : null,
            'expires_at' => $expiresAt,
        ]);
    }

    /** Contoh nomor VA / kode bayar / QR supaya tampilan halaman bayar bisa dicoba tanpa API key. */
    public static function instructionsFor(PaymentMethod $method, Order $order): ?array
    {
        $digits = str_pad((string) ($order->id % 10_000_000_000), 10, '0', STR_PAD_LEFT);

        return match ($method->channel()) {
            PaymentMethod::CHANNEL_VA => ['number' => '88'.substr(sprintf('%u', crc32($method->value)), 0, 4).$digits],
            PaymentMethod::CHANNEL_BILL => ['biller_code' => '70012', 'bill_key' => '9'.$digits.'1'],
            PaymentMethod::CHANNEL_QR => ['qr_string' => 'FAKE-QRIS-'.$order->code],
            PaymentMethod::CHANNEL_STORE => ['payment_code' => 'MGA'.$digits],
            default => null,
        };
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
