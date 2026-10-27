<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentCharge;
use App\Data\PaymentUpdate;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Integrasi Midtrans Snap (pembuatan tagihan) + Core API (cek status & batal).
 *
 * @see https://docs.midtrans.com/reference/backend-integration
 * @see https://docs.midtrans.com/docs/https-notification-webhooks
 */
class MidtransPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?string $serverKey,
        private readonly bool $isProduction,
    ) {}

    public function name(): string
    {
        return 'midtrans';
    }

    public function createPayment(Order $order, CarbonInterface $expiresAt, ?PaymentCharge $charge = null): Payment
    {
        $this->ensureConfigured();

        $charge ??= new PaymentCharge(amount: $order->total);

        $order->loadMissing('items', 'user');

        // Midtrans menolak order_id yang pernah dipakai, jadi tagihan ulang diberi akhiran.
        $attempt = $order->payments()->where('gateway', $this->name())->count() + 1;
        $externalId = $order->code.($attempt > 1 ? '-'.$attempt : '');

        $items = self::itemDetails($order, $charge);

        // Tanpa start_time, Midtrans menghitung kedaluwarsa sejak pelanggan memilih
        // metode bayar, sehingga bisa melewati batas bayar pesanan.
        $start = now()->timezone('Asia/Jakarta');
        $minutes = max(1, (int) ceil($start->diffInSeconds($expiresAt) / 60));

        $response = $this->http()
            ->post($this->snapBaseUrl().'/snap/v1/transactions', [
                'transaction_details' => [
                    'order_id' => $externalId,
                    'gross_amount' => $charge->amount,
                ],
                'item_details' => $items,
                ...($charge->enabledPayments ? ['enabled_payments' => $charge->enabledPayments] : []),
                'customer_details' => [
                    'first_name' => mb_substr($order->recipient_name, 0, 255),
                    'email' => $order->user->email,
                    'phone' => $order->recipient_phone,
                ],
                'expiry' => [
                    'start_time' => $start->format('Y-m-d H:i:s O'),
                    'unit' => 'minutes',
                    'duration' => $minutes,
                ],
                'callbacks' => [
                    'finish' => route('account.orders.payment.finish', $order),
                ],
            ])
            ->throw();

        if (! $response->json('token')) {
            throw new RuntimeException('Midtrans tidak mengembalikan Snap token.');
        }

        return $order->payments()->create([
            'gateway' => $this->name(),
            'external_id' => $externalId,
            'status' => PaymentStatus::Pending,
            'amount' => $charge->amount,
            'fee' => $charge->fee,
            'gateway_fee' => $charge->gatewayFee,
            'method' => $charge->method?->value,
            'snap_token' => $response->json('token'),
            'redirect_url' => $response->json('redirect_url'),
            'expires_at' => $start->copy()->addMinutes($minutes),
        ]);
    }

    /**
     * Rincian item untuk Snap. Jumlah semua item harus sama dengan gross_amount,
     * jadi biaya layanan, PPN (bila belum termasuk harga), pembulatan, dan
     * biaya transaksi ikut menjadi item; potongan voucher bernilai negatif.
     *
     * @return list<array{id: string, price: int, quantity: int, name: string}>
     */
    public static function itemDetails(Order $order, PaymentCharge $charge): array
    {
        $items = $order->items->map(fn ($item) => [
            'id' => mb_substr($item->sku ?: (string) ($item->product_id ?? $item->id), 0, 50),
            'price' => $item->price,
            'quantity' => $item->quantity,
            'name' => mb_substr(trim($item->product_name.' '.($item->variant_name ? "({$item->variant_name})" : '')), 0, 50),
        ])->values()->all();

        $extra = [
            'SHIPPING' => [$order->shipping_cost, 'Ongkir '.$order->courierLabel()],
            'VOUCHER' => [-$order->discountTotal(), 'Voucher '.$order->voucher_code],
            'SERVICE' => [(int) $order->service_fee, config('shop.billing.service_fee_label') ?: 'Biaya layanan'],
            'TAX' => [$order->tax_inclusive ? 0 : (int) $order->tax_amount, $order->taxLabel()],
            'ROUNDING' => [(int) $order->rounding, 'Pembulatan'],
            'FEE' => [$charge->fee, $charge->feeLabel.($charge->method ? ' '.$charge->method->shortLabel() : '')],
        ];

        foreach ($extra as $id => [$price, $name]) {
            if ($price !== 0) {
                $items[] = ['id' => $id, 'price' => $price, 'quantity' => 1, 'name' => mb_substr(trim($name), 0, 50)];
            }
        }

        // Pengaman: selisih (mis. total minimum Rp1) disamakan supaya Snap tidak menolak.
        $difference = $charge->amount - array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items));

        if ($difference !== 0) {
            $items[] = ['id' => 'ADJUSTMENT', 'price' => $difference, 'quantity' => 1, 'name' => 'Penyesuaian'];
        }

        return $items;
    }

    public function parseNotification(array $payload): ?PaymentUpdate
    {
        if (! $this->hasValidSignature($payload)) {
            return null;
        }

        return $this->toUpdate($payload);
    }

    public function fetchStatus(Payment $payment): ?PaymentUpdate
    {
        $this->ensureConfigured();

        $response = $this->http()->get($this->apiBaseUrl().'/v2/'.rawurlencode($this->externalId($payment)).'/status');

        // Snap token yang belum dipakai memilih metode bayar belum tercatat sebagai transaksi.
        if ($response->status() === 404 || $response->json('status_code') === '404') {
            return null;
        }

        $response->throw();

        if (! $response->json('transaction_status')) {
            throw new RuntimeException('Respons status Midtrans tidak dikenali: '.$response->json('status_message'));
        }

        return $this->toUpdate($response->json());
    }

    public function cancel(Payment $payment): void
    {
        $this->ensureConfigured();

        $id = rawurlencode($this->externalId($payment));
        $response = $this->http()->post($this->apiBaseUrl()."/v2/{$id}/cancel");
        $code = (string) $response->json('status_code', $response->status());

        // 404: pelanggan belum memilih metode (tidak ada yang perlu dibatalkan).
        if (in_array($code, ['200', '404'], true)) {
            return;
        }

        // Beberapa metode (VA, e-wallet) yang masih pending hanya bisa di-"expire".
        $expire = $this->http()->post($this->apiBaseUrl()."/v2/{$id}/expire");
        $expireCode = (string) $expire->json('status_code', $expire->status());

        if (! in_array($expireCode, ['200', '404', '407'], true)) {
            Log::warning('Transaksi Midtrans gagal dibatalkan', [
                'order_id' => $payment->external_id,
                'cancel' => $response->json('status_message'),
                'expire' => $expire->json('status_message'),
            ]);
        }
    }

    /** Terjemahkan transaction_status + fraud_status Midtrans ke status aplikasi. */
    public static function mapStatus(?string $status, ?string $fraud): ?PaymentStatus
    {
        return match ($status) {
            'settlement' => PaymentStatus::Paid,
            'capture' => match ($fraud ?? 'accept') {
                'accept' => PaymentStatus::Paid,
                'deny' => PaymentStatus::Failed,
                default => PaymentStatus::Pending, // challenge: menunggu keputusan di dashboard Midtrans
            },
            'pending', 'authorize' => PaymentStatus::Pending,
            'deny', 'failure' => PaymentStatus::Failed,
            'cancel' => PaymentStatus::Cancelled,
            'expire' => PaymentStatus::Expired,
            'refund', 'chargeback' => PaymentStatus::Refunded,
            default => null, // partial_refund, partial_chargeback, dll: status tidak diubah
        };
    }

    private function toUpdate(array $data): ?PaymentUpdate
    {
        if (! isset($data['order_id'])) {
            return null;
        }

        return new PaymentUpdate(
            externalId: (string) $data['order_id'],
            status: self::mapStatus($data['transaction_status'] ?? null, $data['fraud_status'] ?? null),
            amount: isset($data['gross_amount']) ? (int) round((float) $data['gross_amount']) : null,
            reference: $data['transaction_id'] ?? null,
            paymentType: $data['payment_type'] ?? null,
            payload: $data,
        );
    }

    /** signature_key = sha512(order_id + status_code + gross_amount + server_key) */
    private function hasValidSignature(array $payload): bool
    {
        if (! $this->serverKey) {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key])) {
                return false;
            }
        }

        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$this->serverKey);

        return hash_equals($expected, $payload['signature_key']);
    }

    /** Tagihan lama (sebelum kolom external_id ada) memakai kode pesanan. */
    private function externalId(Payment $payment): string
    {
        return $payment->external_id ?? $payment->order->code;
    }

    private function ensureConfigured(): void
    {
        if (! $this->serverKey) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diatur.');
        }
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth((string) $this->serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(5);
    }

    private function snapBaseUrl(): string
    {
        return $this->isProduction ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    private function apiBaseUrl(): string
    {
        return $this->isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }
}
