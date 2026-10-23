<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'shop.payment.driver' => 'midtrans',
            'services.midtrans.server_key' => self::SERVER_KEY,
        ]);
    }

    public function test_settlement_marks_order_paid(): void
    {
        $order = $this->orderWithPayment();

        $this->postJson(route('webhooks.midtrans'), $this->payload($order, 'settlement'))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->latestPayment->status);
        $this->assertSame('bank_transfer', $order->latestPayment->payment_type);
    }

    public function test_expired_payment_cancels_order(): void
    {
        $order = $this->orderWithPayment();

        $this->postJson(route('webhooks.midtrans'), $this->payload($order, 'expire'))->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Expired, $order->latestPayment->status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->orderWithPayment();
        $payload = $this->payload($order, 'settlement');
        $payload['signature_key'] = 'palsu';

        $this->postJson(route('webhooks.midtrans'), $payload)->assertForbidden();
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_snap_token_is_requested_when_creating_payment(): void
    {
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'snap-123', 'redirect_url' => 'https://pay.test'])]);

        $order = $this->orderWithPayment(withPayment: false);
        $payment = app(\App\Contracts\PaymentGateway::class)->createPayment($order);

        $this->assertSame('snap-123', $payment->snap_token);
        Http::assertSent(fn ($request) => $request['transaction_details']['gross_amount'] === 30000
            && $request['transaction_details']['order_id'] === $order->code);
    }

    private function orderWithPayment(bool $withPayment = true): Order
    {
        $order = User::factory()->create()->orders()->create([
            'code' => Order::generateCode(),
            'subtotal' => 20000, 'shipping_cost' => 10000, 'total' => 30000,
            'recipient_name' => 'Budi', 'recipient_phone' => '0812', 'shipping_address' => 'Bandung',
            'courier' => 'jne', 'courier_service' => 'REG',
        ]);

        if ($withPayment) {
            $order->payments()->create(['gateway' => 'midtrans', 'status' => PaymentStatus::Pending, 'amount' => 30000]);
        }

        return $order;
    }

    private function payload(Order $order, string $status): array
    {
        $gross = '30000.00';
        $code = $status === 'settlement' ? '200' : '407';

        return [
            'order_id' => $order->code,
            'status_code' => $code,
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'transaction_id' => 'trx-1',
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $order->code.$code.$gross.self::SERVER_KEY),
        ];
    }
}
