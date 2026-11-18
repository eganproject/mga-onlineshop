<?php

namespace Tests\Feature;

use App\Data\PaymentUpdate;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Penjualan manual dari admin: input pesanan, cek ongkir, tagihan QRIS/VA, link tagihan. */
class ManualOrderTest extends TestCase
{
    private function cashPayment(array $overrides = []): array
    {
        return $overrides + ['method' => 'cash', 'paid_at' => now()->format('Y-m-d\\TH:i')];
    }

    use RefreshDatabase;

    private function staff(StaffRole $role = StaffRole::CustomerService): User
    {
        return User::factory()->staff($role)->create();
    }

    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'customer_name' => 'Budi Pembeli',
            'customer_phone' => '081234567890',
            'shipping_mode' => 'pickup',
        ], $overrides);
    }

    public function test_cs_can_create_manual_order_without_customer_account(): void
    {
        $product = Product::factory()->create(['price' => 50_000, 'stock' => 10]);
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.orders.create'))->assertOk()->assertSee('Penjualan manual');

        $response = $this->post(route('admin.orders.store'), $this->payload($product, ['manual_discount' => 5_000, 'notes' => 'Via WhatsApp']));

        $order = Order::sole();
        $response->assertRedirect(route('admin.orders.show', $order));

        $this->assertTrue($order->isManual());
        $this->assertNull($order->user_id);
        $this->assertSame($staff->id, $order->created_by);
        $this->assertSame(100_000, $order->subtotal);
        $this->assertSame(5_000, $order->manual_discount);
        $this->assertSame(95_000, $order->total);
        $this->assertSame('Ambil di toko', $order->shipping_address);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(8, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'quantity' => -2]);

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Manual')
            ->assertSee('Diskon khusus')
            ->assertSee('Tagihan pembayaran')
            ->assertSee('Budi Pembeli');
    }

    public function test_warehouse_staff_cannot_create_manual_orders(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->staff(StaffRole::Warehouse))->get(route('admin.orders.create'))->assertForbidden();
        $this->post(route('admin.orders.store'), $this->payload($product))->assertForbidden();
        $this->assertSame(0, Order::count());
    }

    public function test_courier_shipping_uses_rajaongkir_rate_and_region_names(): void
    {
        $product = Product::factory()->create(['price' => 100_000, 'stock' => 5, 'weight' => 600]);
        $this->actingAs($this->staff());

        $rates = $this->getJson(route('admin.orders.manual.rates', ['district_id' => '20101', 'weight' => 1200]))->assertOk()->json('data');
        $this->assertNotEmpty($rates);
        $rate = $rates[0];

        $this->post(route('admin.orders.store'), $this->payload($product, [
            'shipping_mode' => 'courier',
            'province_id' => '2', 'city_id' => '201', 'district_id' => '20101',
            'address_line' => 'Jl. Dago 1',
            'rate' => $rate['key'],
        ]))->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame($rate['cost'], $order->shipping_cost);
        $this->assertSame(200_000 + $rate['cost'], $order->total);
        $this->assertSame('Jl. Dago 1, Coblong, Kota Bandung, Jawa Barat', $order->shipping_address);
        $this->assertSame(explode('|', $rate['key'])[0], $order->courier);
        $this->assertSame(1200, $order->total_weight);
    }

    public function test_invalid_rate_or_stock_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 1]);
        $this->actingAs($this->staff());

        $this->post(route('admin.orders.store'), $this->payload($product))->assertSessionHasErrors('items.0.product_id');

        $this->post(route('admin.orders.store'), $this->payload($product, [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_mode' => 'courier',
            'province_id' => '2', 'city_id' => '201', 'district_id' => '20101',
            'address_line' => 'Jl. Dago 1',
            'rate' => 'xyz|NOPE',
        ]))->assertSessionHasErrors('rate');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->refresh()->stock);
    }

    public function test_staff_can_override_unit_price_per_line(): void
    {
        $product = Product::factory()->create(['price' => 20_000, 'stock' => 100, 'sku' => 'NEGO-1', 'wholesale_tiers' => [['min_qty' => 10, 'price' => 15_000]]]);
        $other = Product::factory()->create(['price' => 30_000, 'stock' => 10]);
        $this->actingAs($this->staff());

        $items = [
            ['product_id' => $product->id, 'quantity' => 10, 'price' => 12_500],
            ['product_id' => $other->id, 'quantity' => 1, 'price' => null],
        ];

        $quote = $this->postJson(route('admin.orders.manual.quote'), ['items' => $items, 'shipping_mode' => 'pickup'])->assertOk()->json();
        $this->assertSame(12_500, $quote['lines'][0]['price']);
        $this->assertSame(15_000, $quote['lines'][0]['web_price']);
        $this->assertTrue($quote['lines'][0]['custom']);
        $this->assertNull($quote['lines'][0]['regular_price']);
        $this->assertFalse($quote['lines'][1]['custom']);
        $this->assertSame(125_000 + 30_000, $quote['total']);

        $this->post(route('admin.orders.store'), $this->payload($product, ['items' => $items]))->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame(155_000, $order->subtotal);
        $this->assertSame(155_000, $order->total);
        $line = $order->items->firstWhere('product_id', $product->id);
        $this->assertSame(12_500, $line->price);
        $this->assertSame(125_000, $line->subtotal);
        $this->assertSame(15_000, $line->list_price);
        $this->assertTrue($line->hasCustomPrice());
        $this->assertFalse($line->isWholesale());
        $this->assertSame(-25_000, $line->customPriceDifference());
        $this->assertNull($order->items->firstWhere('product_id', $other->id)->list_price);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Harga diubah');

        $this->post(route('admin.orders.store'), $this->payload($product, ['items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 0]]]))
            ->assertSessionHasErrors('items.0.price');
    }

    public function test_quote_previews_wholesale_voucher_and_problems(): void
    {
        $product = Product::factory()->create(['price' => 20_000, 'stock' => 100, 'wholesale_tiers' => [['min_qty' => 10, 'price' => 15_000]]]);
        Voucher::factory()->create(['code' => 'HEMAT10', 'type' => VoucherType::Percent, 'value' => 10, 'min_spend' => 0]);
        $this->actingAs($this->staff());

        $quote = $this->postJson(route('admin.orders.manual.quote'), [
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
            'shipping_mode' => 'custom', 'shipping_cost' => 12_000, 'courier_name' => 'Gojek',
            'voucher_code' => 'hemat10',
        ])->assertOk()->json();

        $this->assertSame(15_000, $quote['lines'][0]['price']);
        $this->assertSame(20_000, $quote['lines'][0]['regular_price']);
        $this->assertSame(12_000, $quote['shipping_cost']);
        $this->assertNull($quote['voucher_error']);
        $this->assertSame(15_000, $quote['discount']);
        $this->assertSame(150_000 - 15_000 + 12_000, $quote['total']);

        $quote = $this->postJson(route('admin.orders.manual.quote'), [
            'items' => [['product_id' => $product->id, 'quantity' => 500]],
            'shipping_mode' => 'pickup', 'voucher_code' => 'NGACO',
        ])->json();

        $this->assertSame('Kode voucher tidak ditemukan.', $quote['voucher_error']);
        $this->assertStringContainsString('tinggal 100 pcs', $quote['problems'][0]);
    }

    public function test_customer_email_links_order_to_account_and_applies_voucher(): void
    {
        $customer = User::factory()->create(['email' => 'langganan@example.com']);
        $product = Product::factory()->create(['price' => 100_000, 'stock' => 5]);
        Voucher::factory()->create(['code' => 'POTONG', 'type' => VoucherType::Fixed, 'value' => 10_000, 'min_spend' => 0, 'per_user_limit' => 1]);
        $this->actingAs($this->staff());

        $this->post(route('admin.orders.store'), $this->payload($product, ['customer_email' => 'langganan@example.com', 'voucher_code' => 'POTONG']))
            ->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('POTONG', $order->voucher_code);
        $this->assertSame(10_000, $order->discount);

        // Batas per pelanggan tetap berlaku untuk akun yang sama.
        $this->post(route('admin.orders.store'), $this->payload($product, ['customer_email' => 'langganan@example.com', 'voucher_code' => 'POTONG']))
            ->assertSessionHasErrors('voucher_code');
    }

    public function test_staff_creates_qris_bill_and_buyer_pays_via_shared_link(): void
    {
        $product = Product::factory()->create(['price' => 75_000, 'stock' => 5]);
        $this->actingAs($this->staff());
        $this->post(route('admin.orders.store'), $this->payload($product, ['items' => [['product_id' => $product->id, 'quantity' => 1]]]));
        $order = Order::sole();

        $this->post(route('admin.orders.payment.choose', $order), ['method' => 'qris'])->assertSessionHas('success');
        $payment = $order->payments()->sole();
        $this->assertSame('qris', $payment->method);
        $this->assertNotNull($payment->instruction('qr_string'));

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Kirim via WhatsApp')->assertSee('Cek status');

        // Kartu kredit tidak bisa dibuat dari admin.
        $this->post(route('admin.orders.payment.choose', $order), ['method' => 'credit_card'])->assertSessionHasErrors('method');

        // Pembeli membuka link tagihan tanpa login.
        auth()->logout();
        $url = $order->billUrl();
        $this->get($url)->assertOk()->assertSee($order->code)->assertSee('QRIS');
        $this->get(route('bill.show', $order))->assertForbidden();

        app(PaymentService::class)->apply(new PaymentUpdate(
            externalId: $payment->external_id, status: PaymentStatus::Paid, amount: $payment->amount, paymentType: 'qris',
        ));

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame('qris', $order->payment_method);
        $this->get($url)->assertOk()->assertSee('Lunas');
        $this->getJson(URL::signedRoute('bill.status', $order))->assertJson(['status' => 'paid']);
    }

    public function test_buyer_can_choose_method_on_bill_page(): void
    {
        $product = Product::factory()->create(['price' => 75_000, 'stock' => 5]);
        $this->actingAs($this->staff());
        $this->post(route('admin.orders.store'), $this->payload($product));
        $order = Order::sole();
        auth()->logout();

        $this->get($order->billUrl())->assertOk()->assertSee('Pilih metode pembayaran');
        $this->post(URL::signedRoute('bill.method', $order), ['method' => 'bca_va'])->assertRedirect();

        $this->assertSame('bca_va', $order->payments()->sole()->method);
        $this->get($order->billUrl())->assertOk()->assertSee('Nomor Virtual Account');

        $this->post(route('bill.method', $order), ['method' => 'qris'])->assertForbidden();
    }

    public function test_record_bank_transfer_with_proof_and_past_date(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['price' => 30_000, 'stock' => 5]);
        $staff = $this->staff();
        $this->actingAs($staff)->post(route('admin.orders.store'), $this->payload($product));
        $order = Order::sole();

        $this->get(route('admin.orders.show', $order))->assertSee('Catat pembayaran di luar Midtrans');

        // Transfer wajib referensi; tanggal tidak boleh di masa depan.
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment(['method' => 'bank_transfer']))->assertSessionHasErrors('reference');
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment(['method' => 'cash', 'paid_at' => now()->addDay()->format('Y-m-d\\TH:i')]))->assertSessionHasErrors('paid_at');
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);

        $paidAt = now()->subMinutes(30)->startOfMinute();
        $this->post(route('admin.orders.offline-payment', $order), [
            'method' => 'bank_transfer',
            'paid_at' => $paidAt->copy()->timezone(config('app.timezone'))->format('Y-m-d\\TH:i'),
            'reference' => 'BCA a.n. Budi 123',
            'note' => 'Transfer ke rekening toko',
            'proof' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertTrue($order->paid_at->equalTo($paidAt));
        $this->assertSame('BCA a.n. Budi 123', $order->payment_reference);
        $this->assertSame($staff->id, $order->payment_recorded_by);
        $this->assertNotNull($order->invoice_number);
        Storage::disk('local')->assertExists($order->payment_proof);
        $this->assertDatabaseHas('activity_logs', ['action' => 'order.payment_recorded']);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Transfer bank langsung')->assertSee('BCA a.n. Budi 123')->assertSee('Lihat bukti bayar');
        $this->get(route('admin.orders.payment-proof', $order))->assertOk();

        // Sudah lunas: tidak bisa dicatat lagi.
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment())->assertSessionHas('error');
    }

    public function test_recording_payment_needs_its_own_permission(): void
    {
        $product = Product::factory()->create(['price' => 30_000, 'stock' => 5]);
        $this->actingAs($this->staff())->post(route('admin.orders.store'), $this->payload($product));
        $order = Order::sole();

        $warehouse = $this->staff(StaffRole::Warehouse);
        $this->actingAs($warehouse)->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Catat pembayaran di luar Midtrans');
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment())->assertForbidden();
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_mark_paid_cash_and_cancel_restores_stock(): void
    {
        $product = Product::factory()->create(['price' => 30_000, 'stock' => 5]);
        $this->actingAs($this->staff());
        $this->post(route('admin.orders.store'), $this->payload($product));
        $order = Order::sole();
        $this->post(route('admin.orders.payment.choose', $order), ['method' => 'bni_va']);

        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment())->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('Tunai', $order->paymentMethodLabel());
        $this->assertSame(0, $order->gateway_fee);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->sole()->status);

        // Pesanan lain yang dibatalkan mengembalikan stok.
        $this->post(route('admin.orders.store'), $this->payload($product));
        $second = Order::latest('id')->first();
        $this->assertSame(1, $product->refresh()->stock);

        $this->actingAs($this->staff(StaffRole::Admin))->patch(route('admin.orders.status', $second), ['status' => 'cancelled', 'note' => 'Batal']);
        $this->assertSame(3, $product->refresh()->stock);
    }

    public function test_orders_list_filters_by_source_and_shows_guest_customer(): void
    {
        $product = Product::factory()->create(['price' => 30_000, 'stock' => 5]);
        Order::factory()->create(['recipient_name' => 'Pembeli Website']);
        $this->actingAs($this->staff());
        $this->post(route('admin.orders.store'), $this->payload($product, ['customer_name' => 'Pembeli Manual']));

        $this->actingAs($this->staff(StaffRole::Admin))->get(route('admin.orders.index', ['source' => 'manual']))
            ->assertOk()->assertSee('Pembeli Manual')->assertDontSee('Pembeli Website');
        $this->get(route('admin.dashboard'))->assertOk();
    }
}
