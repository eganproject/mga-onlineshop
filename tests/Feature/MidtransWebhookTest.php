<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    private Product $product;

    /** Respons status API Midtrans per order_id; default "belum ada transaksi". */
    private array $statuses = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'shop.payment.driver' => 'midtrans',
            'services.midtrans.server_key' => self::SERVER_KEY,
            'services.midtrans.client_key' => 'SB-Mid-client-test',
        ]);

        $tokens = 0;
        Http::fake(function (Request $request) use (&$tokens) {
            $url = $request->url();

            if (str_contains($url, '/snap/v1/transactions')) {
                $tokens++;

                return Http::response(['token' => "snap-{$tokens}", 'redirect_url' => "https://pay.test/{$tokens}"], 201);
            }

            if (preg_match('#/v2/([^/]+)/status$#', $url, $m)) {
                $id = rawurldecode($m[1]);

                return isset($this->statuses[$id])
                    ? Http::response($this->statuses[$id])
                    : Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404);
            }

            if (preg_match('#/v2/([^/]+)/(cancel|expire)$#', $url)) {
                return Http::response(['status_code' => '200', 'transaction_status' => 'cancel']);
            }

            return Http::response([], 500);
        });
    }

    public function test_checkout_requests_snap_token_with_expiry_and_finish_url(): void
    {
        $order = $this->checkout();
        $payment = $order->latestPayment;

        $this->assertSame('snap-1', $payment->snap_token);
        $this->assertSame($order->code, $payment->external_id);
        $this->assertNotNull($order->payment_due_at);
        $this->assertEqualsWithDelta($order->payment_due_at->timestamp, $payment->expires_at->timestamp, 60);

        Http::assertSent(function (Request $request) use ($order) {
            return str_contains($request->url(), '/snap/v1/transactions')
                && $request['transaction_details']['order_id'] === $order->code
                && $request['transaction_details']['gross_amount'] === $order->total
                && array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $request['item_details'])) === $order->total
                && $request['expiry']['unit'] === 'minutes'
                && $request['expiry']['duration'] === 24 * 60
                && str_ends_with($request['expiry']['start_time'], '+0700')
                && $request['callbacks']['finish'] === route('account.orders.payment.finish', $order);
        });
    }

    public function test_payment_page_shows_snap_and_reuses_active_token(): void
    {
        $order = $this->checkout();

        $this->get(route('account.orders.payment', $order))
            ->assertOk()
            ->assertSee('Bayar sekarang')
            ->assertSee('snap-1', false)
            ->assertSee('snap.js', false)
            ->assertSee('SB-Mid-client-test', false);

        $this->get(route('account.orders.payment', $order))->assertOk();
        $this->assertSame(1, $order->payments()->count());
        Http::assertSentCount(1);
    }

    public function test_settlement_marks_order_paid_and_duplicates_are_ignored(): void
    {
        $order = $this->checkout();

        $this->notify($order->code, 'settlement')->assertOk();

        $order->refresh();
        $payment = $order->latestPayment;
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('bank_transfer', $payment->payment_type);
        $paidAt = $payment->paid_at;

        // Midtrans mengirim ulang notifikasi yang sama, lalu notifikasi "pending" yang terlambat.
        $this->travel(10)->minutes();
        $this->notify($order->code, 'settlement')->assertOk();
        $this->notify($order->code, 'pending')->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertTrue($paidAt->equalTo($payment->paid_at));
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_invalid_signature_unknown_order_and_wrong_amount_are_rejected(): void
    {
        $order = $this->checkout();

        $payload = $this->payload($order->code, 'settlement');
        $payload['signature_key'] = 'palsu';
        $this->postJson(route('webhooks.midtrans'), $payload)->assertForbidden();

        $this->notify('MGA-TIDAK-ADA', 'settlement')->assertNotFound();

        // Signature valid tapi nominal berbeda dari tagihan: tidak dianggap lunas.
        $this->notify($order->code, 'settlement', gross: '1000.00')->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->latestPayment->status);
    }

    public function test_challenged_card_payment_stays_pending(): void
    {
        $order = $this->checkout();

        $this->notify($order->code, 'capture', fraud: 'challenge')->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);

        $this->notify($order->code, 'capture', fraud: 'accept')->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_denied_payment_keeps_order_open_and_customer_can_pay_with_new_link(): void
    {
        $order = $this->checkout();

        $this->notify($order->code, 'deny')->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Failed, $order->latestPayment->status);
        $this->assertSame(4, $this->product->refresh()->stock);

        $this->get(route('account.orders.show', $order))->assertSee('Bayar ulang');

        // Membuka halaman bayar membuat tagihan baru dengan order_id berbeda.
        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('snap-2', false);
        $new = $order->refresh()->latestPayment;
        $this->assertSame($order->code.'-2', $new->external_id);

        $this->notify($order->code.'-2', 'settlement')->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_expire_after_deadline_cancels_order_and_releases_stock(): void
    {
        $order = $this->checkout();

        // Notifikasi expire sebelum batas bayar (mis. link lama) tidak membatalkan pesanan.
        $this->notify($order->code, 'expire')->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Expired, $order->latestPayment->status);

        $this->get(route('account.orders.payment', $order))->assertOk();
        $this->travel(25)->hours();
        $this->notify($order->code.'-2', 'expire')->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $this->product->refresh()->stock);

        // Notifikasi expire ganda tidak mengembalikan stok dua kali.
        $this->notify($order->code.'-2', 'expire')->assertOk();
        $this->assertSame(5, $this->product->refresh()->stock);
    }

    public function test_late_settlement_is_still_recorded(): void
    {
        $order = $this->checkout();
        $this->travel(25)->hours();
        $this->notify($order->code, 'expire')->assertOk();
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);

        $this->notify($order->code, 'settlement')->assertOk();

        // Tagihan tercatat lunas agar admin tahu perlu refund; pesanan tetap batal.
        $this->assertSame(PaymentStatus::Paid, $order->latestPayment()->first()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    public function test_customer_cancel_releases_stock_and_cancels_midtrans_transaction(): void
    {
        $order = $this->checkout();
        $this->statuses[$order->code] = $this->statusResponse($order->code, 'pending');

        $this->post(route('account.orders.cancel', $order))->assertSessionHas('success');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->latestPayment->status);
        $this->assertSame(5, $this->product->refresh()->stock);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/'.$order->code.'/cancel'));
    }

    public function test_customer_cannot_cancel_order_that_midtrans_already_settled(): void
    {
        $order = $this->checkout();
        $this->statuses[$order->code] = $this->statusResponse($order->code, 'settlement');

        $this->post(route('account.orders.cancel', $order))->assertSessionHas('error');

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(4, $this->product->refresh()->stock);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/cancel'));
    }

    public function test_regenerate_cancels_old_transaction_and_creates_new_link(): void
    {
        $order = $this->checkout();
        $this->statuses[$order->code] = $this->statusResponse($order->code, 'pending', type: 'bank_transfer');

        $this->post(route('account.orders.payment.regenerate', $order))
            ->assertRedirect(route('account.orders.payment', $order));

        $payments = $order->payments()->orderBy('id')->get();
        $this->assertSame([PaymentStatus::Cancelled, PaymentStatus::Pending], $payments->pluck('status')->all());
        $this->assertSame('snap-2', $payments[1]->snap_token);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/'.$order->code.'/cancel'));

        // Notifikasi "cancel" untuk link lama tidak membatalkan pesanan.
        $this->notify($order->code, 'cancel')->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_finish_redirect_checks_status_with_midtrans(): void
    {
        $order = $this->checkout();
        $this->statuses[$order->code] = $this->statusResponse($order->code, 'settlement');

        $this->get(route('account.orders.payment.finish', [$order, 'transaction_status' => 'settlement']))
            ->assertRedirect(route('account.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_expire_command_checks_midtrans_before_cancelling(): void
    {
        $paid = $this->checkout();
        $unpaid = $this->checkout();
        $this->statuses[$paid->code] = $this->statusResponse($paid->code, 'settlement');
        $this->statuses[$unpaid->code] = $this->statusResponse($unpaid->code, 'pending');

        $this->artisan('shop:expire-payments')->assertSuccessful();
        $this->assertSame(OrderStatus::PendingPayment, $unpaid->refresh()->status, 'Belum lewat batas bayar.');

        $this->travel(25)->hours();
        $this->artisan('shop:expire-payments')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $paid->refresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $unpaid->refresh()->status);
        $this->assertSame(PaymentStatus::Expired, $unpaid->latestPayment->status);
        $this->assertSame(4, $this->product->refresh()->stock, '5 - 2 terjual + 1 dikembalikan');
    }

    public function test_legacy_payment_without_external_id_is_matched_by_order_code(): void
    {
        $order = $this->checkout();
        Payment::query()->update(['external_id' => null]);

        $this->notify($order->code, 'settlement')->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    private function checkout(): Order
    {
        $this->product ??= Product::factory()->create(['price' => 20000, 'stock' => 5, 'weight' => 300]);
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->post(route('cart.store', $this->product), ['quantity' => 1]);
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'])->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();

        return $order;
    }

    private function notify(string $orderId, string $status, ?string $fraud = null, ?string $gross = null)
    {
        return $this->postJson(route('webhooks.midtrans'), $this->payload($orderId, $status, $fraud, $gross));
    }

    private function payload(string $orderId, string $status, ?string $fraud = null, ?string $gross = null): array
    {
        $order = Order::where('code', $this->orderCode($orderId))->first();
        $gross ??= number_format($order?->total ?? 31000, 2, '.', '');
        $code = match ($status) {
            'settlement', 'capture' => '200',
            'pending' => '201',
            'deny' => '202',
            default => '407',
        };

        return array_filter([
            'order_id' => $orderId,
            'status_code' => $code,
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'fraud_status' => $fraud,
            'transaction_id' => 'trx-'.$orderId,
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $orderId.$code.$gross.self::SERVER_KEY),
        ]);
    }

    private function statusResponse(string $orderId, string $status, string $type = 'bank_transfer'): array
    {
        $order = Order::where('code', $this->orderCode($orderId))->firstOrFail();

        return [
            'status_code' => $status === 'settlement' ? '200' : '201',
            'order_id' => $orderId,
            'gross_amount' => number_format($order->total, 2, '.', ''),
            'transaction_status' => $status,
            'transaction_id' => 'trx-'.$orderId,
            'payment_type' => $type,
        ];
    }

    /** "MGA-261023-ABCDE-2" → "MGA-261023-ABCDE" */
    private function orderCode(string $orderId): string
    {
        return implode('-', array_slice(explode('-', $orderId), 0, 3));
    }
}
