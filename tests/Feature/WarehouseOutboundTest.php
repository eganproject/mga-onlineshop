<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderService;
use App\Services\Warehouse\WarehouseStockSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Penjualan manual lunas → Outbound Manual di warehouse29 (POST /api/v1/outbound-manuals). */
class WarehouseOutboundTest extends TestCase
{
    private function cashPayment(array $overrides = []): array
    {
        return $overrides + ['method' => 'cash', 'paid_at' => now()->format('Y-m-d\\TH:i')];
    }

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.warehouse.outbound_enabled' => true,
            'services.warehouse.base_url' => 'https://gudang.test/',
            'services.warehouse.token' => 'rahasia-gudang',
        ]);
    }

    private function manualOrder(Product $product, int $qty = 2): Order
    {
        $this->actingAs(User::factory()->staff(StaffRole::CustomerService)->create());
        $this->post(route('admin.orders.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
            'customer_name' => 'Budi', 'customer_phone' => '0812345678', 'shipping_mode' => 'pickup',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    private function outboundResponse(string $status = 'pending', ?string $approvedAt = null): array
    {
        return ['success' => true, 'data' => [
            'code' => 'OUT-MNL-20261117140012-AB12', 'ref_no' => 'x', 'status' => $status, 'approved_at' => $approvedAt,
        ]];
    }

    public function test_paid_manual_order_is_sent_as_outbound_with_warehouse_skus(): void
    {
        Http::fake(['gudang.test/api/v1/outbound-manuals' => Http::response($this->outboundResponse(), 201)]);
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 10, 'sku' => 'TOKO-1']);
        $product->forceFill(['warehouse_sku' => 'WH-001'])->save();
        $order = $this->manualOrder($product, 3);

        Http::assertNothingSent(); // belum lunas

        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment())->assertSessionHas('success');

        Http::assertSent(function (Request $request) use ($order) {
            return $request->method() === 'POST'
                && $request->url() === 'https://gudang.test/api/v1/outbound-manuals'
                && $request->hasHeader('Authorization', 'Bearer rahasia-gudang')
                && $request['ref_no'] === $order->code
                && $request['items'] === [['sku' => 'WH-001', 'qty' => 3]]
                && str_contains($request['note'], $order->code);
        });

        $order->refresh();
        $this->assertSame(Order::OUTBOUND_PENDING, $order->warehouse_outbound_status);
        $this->assertSame('OUT-MNL-20261117140012-AB12', $order->warehouse_outbound_code);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Menunggu approve gudang');
    }

    public function test_duplicate_ref_no_counts_as_recorded(): void
    {
        Http::fake(['gudang.test/*' => Http::response(['success' => false, 'error' => ['code' => 'DUPLICATE_REF_NO', 'message' => 'sudah ada']] + $this->outboundResponse(), 409)]);
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->manualOrder($product);
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment());

        $this->assertSame(Order::OUTBOUND_PENDING, $order->refresh()->warehouse_outbound_status);
        Http::assertSentCount(1);
    }

    public function test_paid_website_order_is_also_sent(): void
    {
        Http::fake(['gudang.test/api/v1/outbound-manuals' => Http::response($this->outboundResponse(), 201)]);
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 10, 'sku' => 'TOKO-9']);
        $website = Order::factory()->unpaid()->create();
        $website->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'price' => 10_000, 'quantity' => 2, 'subtotal' => 20_000,
        ]);

        Http::assertNothingSent();
        app(OrderService::class)->transition($website, OrderStatus::Paid);

        Http::assertSent(fn (Request $request) => $request['ref_no'] === $website->code
            && $request['items'] === [['sku' => 'TOKO-9', 'qty' => 2]]
            && str_contains($request['note'], 'Pesanan website'));
        $this->assertSame(Order::OUTBOUND_PENDING, $website->refresh()->warehouse_outbound_status);

        $this->actingAs(User::factory()->staff(StaffRole::CustomerService)->create());
        $this->get(route('admin.orders.show', $website))->assertOk()->assertSee('Menunggu approve gudang');
    }

    public function test_order_paid_before_switch_on_can_be_sent_manually(): void
    {
        Http::fake(['gudang.test/api/v1/outbound-manuals' => Http::response($this->outboundResponse(), 201)]);
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 10]);
        $website = Order::factory()->create(); // sudah lunas, tidak lewat hook
        $website->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'price' => 10_000, 'quantity' => 1, 'subtotal' => 10_000,
        ]);
        Http::assertNothingSent();

        $this->actingAs(User::factory()->staff(StaffRole::CustomerService)->create());
        $this->get(route('admin.orders.show', $website))->assertSee('Kirim ke gudang');
        $this->post(route('admin.orders.warehouse.send', $website))->assertSessionHas('success');
        $this->assertSame(Order::OUTBOUND_PENDING, $website->refresh()->warehouse_outbound_status);
    }

    public function test_validation_error_marks_failed_and_can_be_resent(): void
    {
        Http::fakeSequence('gudang.test/*')
            ->push(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'x', 'fields' => ['items.0.sku' => ['SKU X tidak ditemukan.']]]], 422)
            ->push($this->outboundResponse(), 201);
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->manualOrder($product);
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment());

        $order->refresh();
        $this->assertSame(Order::OUTBOUND_FAILED, $order->warehouse_outbound_status);
        $this->assertStringContainsString('SKU X tidak ditemukan', $order->warehouse_outbound_error);

        $this->get(route('admin.orders.show', $order))->assertSee('Kirim ulang');
        $this->post(route('admin.orders.warehouse.send', $order))->assertSessionHas('success');
        $this->assertSame(Order::OUTBOUND_PENDING, $order->refresh()->warehouse_outbound_status);
    }

    public function test_status_sync_marks_approved_and_stock_is_not_reserved_twice(): void
    {
        Http::fake(['gudang.test/api/v1/outbound-manuals' => Http::response($this->outboundResponse(), 201)]);
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->manualOrder($product, 4);
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment());

        $sync = app(WarehouseStockSync::class);
        $this->assertSame(4, $sync->reservedQuantities()['p'.$product->id] ?? 0);

        Http::fake(['gudang.test/api/v1/outbound-manuals/*' => Http::response($this->outboundResponse('approved', '2026-11-17T15:30:00+07:00'))]);
        $this->artisan('warehouse:sync-outbound')->expectsOutputToContain('1 outbound')->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::OUTBOUND_APPROVED, $order->warehouse_outbound_status);
        $this->assertNotNull($order->warehouse_outbound_approved_at);
        $this->assertSame(0, $sync->reservedQuantities()['p'.$product->id] ?? 0);
    }

    public function test_deleted_in_warehouse_and_disabled_setting(): void
    {
        Http::fake(['gudang.test/api/v1/outbound-manuals' => Http::response($this->outboundResponse(), 201)]);
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 10]);
        $order = $this->manualOrder($product);
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment());

        Http::fake(['gudang.test/api/v1/outbound-manuals/*' => Http::response(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'x']], 404)]);
        $this->post(route('admin.orders.warehouse.refresh', $order))->assertSessionHas('success');
        $this->assertSame(Order::OUTBOUND_DELETED, $order->refresh()->warehouse_outbound_status);

        config(['services.warehouse.outbound_enabled' => false]);
        $other = $this->manualOrder($product);
        $this->post(route('admin.orders.offline-payment', $other), $this->cashPayment());
        $this->assertNull($other->refresh()->warehouse_outbound_status);
    }

    public function test_unmapped_product_without_sku_fails_with_clear_message(): void
    {
        Http::fake();
        $product = Product::factory()->create(['price' => 10_000, 'stock' => 10, 'sku' => null]);
        $order = $this->manualOrder($product);
        $this->post(route('admin.orders.offline-payment', $order), $this->cashPayment());

        $order->refresh();
        $this->assertSame(Order::OUTBOUND_FAILED, $order->warehouse_outbound_status);
        $this->assertStringContainsString('SKU gudang belum terhubung', $order->warehouse_outbound_error);
        Http::assertNothingSent();
    }
}
