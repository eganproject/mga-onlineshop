<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integrasi Midtrans Snap.
 *
 * Kerangka awal: pembuatan Snap token dan verifikasi webhook sudah ada,
 * tetapi perlu diuji dengan server key sandbox sebelum produksi.
 *
 * @see https://docs.midtrans.com/reference/backend-integration
 */
class MidtransPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ?string $serverKey,
        private readonly bool $isProduction,
    ) {}

    public function name(): string
    {
        return 'midtrans';
    }

    public function createPayment(Order $order): Payment
    {
        if (! $this->serverKey) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diatur.');
        }

        $order->loadMissing('items', 'user');

        $items = $order->items->map(fn ($item) => [
            'id' => (string) ($item->product_id ?? $item->id),
            'price' => $item->price,
            'quantity' => $item->quantity,
            'name' => mb_substr($item->product_name, 0, 50),
        ])->values()->all();

        if ($order->shipping_cost > 0) {
            $items[] = [
                'id' => 'SHIPPING',
                'price' => $order->shipping_cost,
                'quantity' => 1,
                'name' => mb_substr('Ongkir '.$order->courierLabel(), 0, 50),
            ];
        }

        $response = Http::withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->post($this->snapUrl(), [
                'transaction_details' => [
                    'order_id' => $order->code,
                    'gross_amount' => $order->total,
                ],
                'item_details' => $items,
                'customer_details' => [
                    'first_name' => $order->recipient_name,
                    'email' => $order->user->email,
                    'phone' => $order->recipient_phone,
                ],
                'expiry' => [
                    'unit' => 'hours',
                    'duration' => config('shop.payment.expiry_hours'),
                ],
            ])
            ->throw();

        return $order->payments()->create([
            'gateway' => $this->name(),
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'snap_token' => $response->json('token'),
            'redirect_url' => $response->json('redirect_url'),
            'expires_at' => now()->addHours(config('shop.payment.expiry_hours')),
        ]);
    }

    public function handleNotification(array $payload): ?Payment
    {
        if (! $this->hasValidSignature($payload)) {
            return null;
        }

        $order = Order::where('code', $payload['order_id'])->first();
        $payment = $order?->payments()->where('gateway', $this->name())->latest('id')->first();

        if (! $payment) {
            return null;
        }

        $attributes = [
            'reference' => $payload['transaction_id'] ?? $payment->reference,
            'payment_type' => $payload['payment_type'] ?? null,
            'payload' => $payload,
        ];

        $status = $payload['transaction_status'] ?? null;
        $fraud = $payload['fraud_status'] ?? 'accept';

        match (true) {
            $status === 'settlement',
            $status === 'capture' && $fraud === 'accept' => $this->orders->markPaid($payment, $attributes),
            $status === 'expire' => $this->orders->markPaymentFailed($payment, PaymentStatus::Expired, $attributes),
            in_array($status, ['deny', 'cancel', 'failure'], true) => $this->orders->markPaymentFailed($payment, PaymentStatus::Failed, $attributes),
            default => $payment->fill($attributes)->save(),
        };

        return $payment->refresh();
    }

    /** signature_key = sha512(order_id + status_code + gross_amount + server_key) */
    private function hasValidSignature(array $payload): bool
    {
        if (! $this->serverKey || ! isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key'])) {
            return false;
        }

        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$this->serverKey);

        return hash_equals($expected, $payload['signature_key']);
    }

    private function snapUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }
}
