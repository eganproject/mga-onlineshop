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

    private bool $rejectCharges = false;

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

            if (str_ends_with($url, '/v2/charge')) {
                return Http::response($this->chargeResponse($request->data()), 201);
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

    public function test_choosing_va_charges_core_api_with_expiry_and_shows_number_inline(): void
    {
        $order = $this->checkout(method: null);
        $this->assertSame(0, $order->payments()->count(), 'Checkout belum membuat tagihan.');
        Http::assertNothingSent();

        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('Pilih metode pembayaran')->assertDontSee('snap.js', false);

        $this->post(route('account.orders.payment.method', $order), ['method' => 'bca_va'])
            ->assertRedirect(route('account.orders.payment', $order));
        $payment = $order->latestPayment()->first();

        $this->assertSame($order->code, $payment->external_id);
        $this->assertSame('bca_va', $payment->method);
        $this->assertSame('8808123456789', $payment->instruction('number'));
        $this->assertNull($payment->snap_token);
        $this->assertEqualsWithDelta($order->payment_due_at->timestamp, $payment->expires_at->timestamp, 60);

        Http::assertSent(function (Request $request) use ($order) {
            return str_ends_with($request->url(), 'api.sandbox.midtrans.com/v2/charge')
                && $request['payment_type'] === 'bank_transfer'
                && $request['bank_transfer']['bank'] === 'bca'
                && $request['transaction_details']['order_id'] === $order->code
                && $request['transaction_details']['gross_amount'] === $order->total
                && array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $request['item_details'])) === $order->total
                && $request['custom_expiry']['unit'] === 'minute'
                && $request['custom_expiry']['expiry_duration'] === 24 * 60
                && str_ends_with($request['custom_expiry']['order_time'], '+0700');
        });

        // Halaman bayar menampilkan nomor VA langsung (tanpa popup) dan tidak membuat tagihan lagi.
        $this->get(route('account.orders.payment', $order))
            ->assertOk()
            ->assertSee('Nomor Virtual Account BCA')
            ->assertSee('8808 1234 5678 9')
            ->assertSee('BCA mobile (m-BCA)')
            ->assertDontSee('snap.js', false);
        $this->get(route('account.orders.payment', $order))->assertOk();
        $this->assertSame(1, $order->payments()->count());
        Http::assertSentCount(1);
    }

    public function test_each_method_sends_its_core_api_parameters(): void
    {
        $order = $this->checkout(method: null);

        foreach (['qris' => 'qris', 'gopay' => 'gopay', 'shopeepay' => 'shopeepay', 'mandiri_bill' => 'echannel', 'permata_va' => 'bank_transfer', 'alfamart' => 'cstore'] as $method => $type) {
            $this->post(route('account.orders.payment.method', $order), ['method' => $method])->assertRedirect(route('account.orders.payment', $order));
            Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/charge') && $r['payment_type'] === $type);
        }

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/charge') && ($r['qris']['acquirer'] ?? null) === 'gopay');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/charge') && ($r['cstore']['store'] ?? null) === 'alfamart');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/charge') && ($r['gopay']['callback_url'] ?? null) === route('account.orders.payment.finish', $order));

        // Tagihan terakhir (Alfamart) tampil sebagai kode pembayaran; yang lain sudah dibatalkan.
        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('Kode pembayaran Alfamart')->assertSee('ALFA 1234');
        $this->assertSame(1, $order->payments()->where('status', PaymentStatus::Pending)->count());
    }

    public function test_qris_and_gopay_show_qr_and_app_button(): void
    {
        $order = $this->checkout(method: 'gopay');

        $this->get(route('account.orders.payment', $order))
            ->assertOk()
            ->assertSee('https://api.sandbox.midtrans.com/v2/gopay/qr', false)
            ->assertSee('gojek://gopay/merchanttransfer', false)
            ->assertSee('Buka aplikasi GoPay');

        $this->post(route('account.orders.payment.method', $order), ['method' => 'qris']);
        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('00020101021226', false)->assertSee('Simpan QR');
    }

    public function test_card_uses_embedded_snap_limited_to_card(): void
    {
        $order = $this->checkout(method: 'credit_card');
        $payment = $order->latestPayment()->first();

        $this->assertSame('snap-1', $payment->snap_token);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/snap/v1/transactions')
            && $r['enabled_payments'] === ['credit_card']
            && $r['expiry']['unit'] === 'minutes'
            && $r['callbacks']['finish'] === route('account.orders.payment.finish', $order));

        $this->get(route('account.orders.payment', $order))
            ->assertOk()
            ->assertSee('snap-container', false)
            ->assertSee('snap-1', false)
            ->assertSee('snap.js', false)
            ->assertSee('SB-Mid-client-test', false);
    }

    public function test_rejected_charge_lets_customer_pick_another_method(): void
    {
        $order = $this->checkout(method: null);
        $this->rejectCharges = true;

        $this->post(route('account.orders.payment.method', $order), ['method' => 'bri_va'])
            ->assertRedirect(route('account.orders.payment', $order))
            ->assertSessionHas('error', 'BRI Virtual Account sedang tidak bisa dipakai. Silakan coba lagi atau pilih metode lain.');

        $this->assertSame(0, $order->payments()->count());
        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('Pilih metode pembayaran');
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

    public function test_settlement_records_payment_method_and_estimated_midtrans_fee(): void
    {
        $order = $this->checkout();

        $payload = $this->payload($order->code, 'settlement') + ['va_numbers' => [['bank' => 'bca', 'va_number' => '12345']]];
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();

        $order->refresh();
        $this->assertSame('bca_va', $order->payment_method);
        $this->assertSame(4000, $order->gateway_fee);
        $this->assertSame(0, $order->payment_fee);
        $this->assertSame($order->total - 4000, $order->netReceived());
        $this->assertNotNull($order->invoice_number);
    }

    public function test_buyer_fee_is_sent_as_fee_item_for_chosen_method(): void
    {
        config(['shop.billing.payment_methods' => ['qris' => ['percent' => 0.7, 'charge_to' => 'buyer']]]);
        $order = $this->checkout(method: null);
        Http::assertNothingSent();

        $this->post(route('account.orders.payment.method', $order), ['method' => 'qris'])->assertRedirect();
        $payment = $order->latestPayment()->first();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/charge')
            && $request['payment_type'] === 'qris'
            && $request['transaction_details']['gross_amount'] === $payment->amount
            && collect($request['item_details'])->firstWhere('id', 'FEE')['price'] === $payment->fee
            && array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $request['item_details'])) === $payment->amount);

        $payload = $this->payload($order->code, 'settlement', gross: number_format($payment->amount, 2, '.', ''));
        $payload['payment_type'] = 'qris';
        $payload['signature_key'] = hash('sha512', $order->code.'200'.$payload['gross_amount'].self::SERVER_KEY);
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame($payment->fee, $order->payment_fee);
        $this->assertSame($order->total + $payment->fee, $order->amountPaid());
    }

    public function test_invalid_signature_is_rejected_and_wrong_amount_is_ignored(): void
    {
        $order = $this->checkout();

        $payload = $this->payload($order->code, 'settlement');
        $payload['signature_key'] = 'palsu';
        $this->postJson(route('webhooks.midtrans'), $payload)->assertForbidden();

        // Signature valid tapi nominal berbeda dari tagihan: tidak dianggap lunas.
        $this->notify($order->code, 'settlement', gross: '1000.00')->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->latestPayment->status);
    }

    public function test_dashboard_test_notification_with_unknown_order_gets_200(): void
    {
        // Tombol "Test notification URL" di dashboard Midtrans memakai order_id contoh.
        $this->notify('payment_notif_test_G123456789_abc', 'settlement')->assertOk();
        $this->notify('MGA-TIDAK-ADA', 'pending')->assertOk();
    }

    public function test_notification_is_acknowledged_when_midtrans_driver_is_off(): void
    {
        config(['shop.payment.driver' => 'fake']);

        $this->notify('payment_notif_test_G123456789_abc', 'settlement')
            ->assertOk()
            ->assertJsonPath('message', 'Midtrans belum diaktifkan di toko, notifikasi diabaikan.');
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

        // Halaman bayar menampilkan pilihan metode lagi; tagihan baru memakai order_id berbeda.
        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('Pilih metode pembayaran');
        $this->post(route('account.orders.payment.method', $order), ['method' => 'bca_va']);
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

        $this->post(route('account.orders.payment.method', $order), ['method' => 'bca_va']);
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

    public function test_manual_paid_confirmation_closes_midtrans_payment_link(): void
    {
        $order = $this->checkout();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'paid'])->assertSessionHas('success');

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->latestPayment->status);
        $this->assertSame('manual', $order->latestPayment->payment_type);
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

        $this->assertSame([PaymentStatus::Cancelled], $order->payments()->pluck('status')->all());
        $this->post(route('account.orders.payment.method', $order), ['method' => 'bni_va']);

        $payments = $order->payments()->orderBy('id')->get();
        $this->assertSame([PaymentStatus::Cancelled, PaymentStatus::Pending], $payments->pluck('status')->all());
        $this->assertSame($order->code.'-2', $payments[1]->external_id);
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

    private function checkout(?string $method = 'bca_va'): Order
    {
        $this->product ??= Product::factory()->create(['price' => 20000, 'stock' => 5, 'weight' => 300]);
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->post(route('cart.store', $this->product), ['quantity' => 1]);
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'])->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();

        if ($method) {
            $this->post(route('account.orders.payment.method', $order), ['method' => $method])
                ->assertRedirect(route('account.orders.payment', $order));
        }

        return $order;
    }

    /** Respons Core API /v2/charge per metode (contoh dari dokumentasi Midtrans). */
    private function chargeResponse(array $body): array
    {
        if ($this->rejectCharges) {
            return ['status_code' => '402', 'status_message' => 'Payment channel is not activated.'];
        }

        $id = $body['transaction_details']['order_id'];
        $base = ['status_code' => '201', 'transaction_status' => 'pending', 'transaction_id' => 'trx-'.$id, 'order_id' => $id, 'payment_type' => $body['payment_type']];

        return $base + match ($body['payment_type']) {
            'bank_transfer' => ($body['bank_transfer']['bank'] === 'permata')
                ? ['permata_va_number' => '8562000123456']
                : ['va_numbers' => [['bank' => $body['bank_transfer']['bank'], 'va_number' => '8808123456789']]],
            'echannel' => ['biller_code' => '70012', 'bill_key' => '990000000260'],
            'qris' => ['qr_string' => '00020101021226620014COM.GO-JEK.WWW', 'actions' => [['name' => 'generate-qr-code', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/'.$id.'/qr-code']]],
            'gopay' => ['actions' => [
                ['name' => 'generate-qr-code', 'url' => 'https://api.sandbox.midtrans.com/v2/gopay/qr'],
                ['name' => 'deeplink-redirect', 'url' => 'gojek://gopay/merchanttransfer?tref=1'],
            ]],
            'shopeepay' => ['actions' => [['name' => 'deeplink-redirect', 'url' => 'https://wsa.wallet.airpay.co.id/universal-link/1']]],
            'cstore' => ['payment_code' => 'ALFA12345678', 'store' => $body['cstore']['store']],
        };
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
