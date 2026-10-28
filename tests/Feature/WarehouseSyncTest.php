<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WarehouseStockItem;
use App\Models\WarehouseSyncRun;
use App\Services\Settings\StoreSettings;
use App\Services\Warehouse\WarehouseStockSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarehouseSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.warehouse.enabled' => true,
            'services.warehouse.base_url' => 'https://gudang.test/',
            'services.warehouse.token' => 'rahasia-gudang',
        ]);
    }

    public function test_sync_sets_stock_from_warehouse_minus_unshipped_website_orders(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $product->forceFill(['warehouse_sku' => 'WH-001'])->save();
        $matchedBySku = Product::factory()->create(['sku' => 'WH-002', 'stock' => 50]);
        $other = Product::factory()->create(['stock' => 7]);

        // 2 pcs di pesanan yang sudah dibayar (belum dikirim), 5 pcs sudah dikirim (sudah keluar dari gudang).
        $this->orderWith($product, 2, OrderStatus::Paid);
        $this->orderWith($product, 5, OrderStatus::Shipped);

        $this->fakeWarehouse([
            $this->row('WH-001', 10),
            $this->row('WH-002', 4.5),
            $this->row('WH-999', 8, 'Barang belum ada di toko'),
        ]);

        $run = app(WarehouseStockSync::class)->run();

        $this->assertSame(WarehouseSyncRun::STATUS_SUCCESS, $run->status, (string) $run->message);
        $this->assertSame(WarehouseSyncRun::MODE_FULL, $run->mode);
        $this->assertSame(3, $run->fetched);
        $this->assertSame(2, $run->updated);
        $this->assertSame(1, $run->unmatched);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(4, $matchedBySku->fresh()->stock);
        $this->assertSame(7, $other->fresh()->stock);

        $movement = StockMovement::where('product_id', $product->id)->latest('id')->first();
        $this->assertSame(StockMovementType::WarehouseSync, $movement->type);
        $this->assertSame(5, $movement->quantity);
        $this->assertStringContainsString('dipesan di website (belum dikirim) 2', $movement->note);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer rahasia-gudang')
            && str_starts_with($request->url(), 'https://gudang.test/api/v1/stocks'));
    }

    public function test_next_sync_only_asks_for_changes_and_follows_pages_with_fixed_until(): void
    {
        $variantProduct = Product::factory()->create(['has_variants' => true]);
        $variant = ProductVariant::factory()->for($variantProduct)->create(['stock' => 1]);
        $variant->forceFill(['warehouse_sku' => 'WH-V1'])->save();

        WarehouseSyncRun::create([
            'mode' => 'full', 'status' => 'success', 'server_time' => '2026-10-28T10:00:00+07:00', 'started_at' => now(), 'finished_at' => now(),
        ]);

        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) $query['page'];

            return Http::response([
                'success' => true,
                'meta' => ['server_time' => '2026-10-28T10:05:00+07:00', 'page' => $page, 'per_page' => 500, 'total' => 2, 'total_pages' => 2],
                'data' => [$page === 1 ? $this->row('WH-V1', 6) : $this->row('WH-X', 1, status: 'deleted')],
            ]);
        });

        $run = app(WarehouseStockSync::class)->run();

        $this->assertSame('success', $run->status, (string) $run->message);
        $this->assertSame(WarehouseSyncRun::MODE_INCREMENTAL, $run->mode);
        $this->assertSame('2026-10-28T10:05:00+07:00', $run->server_time);
        $this->assertSame(6, $variant->fresh()->stock);
        $this->assertSame(6, $variantProduct->fresh()->stock);
        $this->assertSame('deleted', WarehouseStockItem::where('sku', 'WH-X')->value('status'));

        $sent = Http::recorded()->map(function ($pair) {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return $query;
        });
        $this->assertSame('2026-10-28T10:00:00+07:00', $sent[0]['updated_since']);
        $this->assertArrayNotHasKey('updated_until', $sent[0]);
        $this->assertSame('2026-10-28T10:05:00+07:00', $sent[1]['updated_until']);
        $this->assertSame('2', $sent[1]['page']);
    }

    public function test_deleted_or_inactive_warehouse_items_set_stock_to_zero(): void
    {
        $product = Product::factory()->create(['stock' => 9]);
        $product->forceFill(['warehouse_sku' => 'WH-OLD'])->save();

        $this->fakeWarehouse([$this->row('WH-OLD', 15, status: 'deleted')]);
        app(WarehouseStockSync::class)->run();

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_failed_api_call_is_recorded_with_helpful_message(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $product->forceFill(['warehouse_sku' => 'WH-001'])->save();

        Http::fake(['*' => Http::response(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'IP sumber tidak diizinkan mengakses API stok.']], 403)]);

        $run = app(WarehouseStockSync::class)->run();

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Master Data › Akses API', $run->message);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertNull(app(WarehouseStockSync::class)->lastCursor());
    }

    public function test_admin_page_links_sku_and_applies_stock_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Tripod Banner 60x160', 'stock' => 0]);
        WarehouseStockItem::create(['sku' => 'TRP-60', 'name' => 'TRIPOD BANNER 60X160', 'qty' => 12, 'status' => 'active']);

        $this->actingAs($admin)->get(route('admin.stock.warehouse'))
            ->assertOk()
            ->assertSee('TRP-60')
            ->assertSee('Hubungkan saran');

        $this->post(route('admin.stock.warehouse.link'), ['sku' => 'TRP-60', 'target' => 'p:'.$product->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('TRP-60', $product->fresh()->warehouse_sku);
        $this->assertSame(12, $product->fresh()->stock);
        $this->assertSame(0, WarehouseStockItem::unlinked()->count());

        $this->get(route('admin.stock.warehouse', ['tab' => 'terhubung']))->assertOk()->assertSee('Tripod Banner 60x160');

        // Satu SKU gudang hanya untuk satu produk.
        $second = Product::factory()->create();
        $this->post(route('admin.stock.warehouse.link'), ['sku' => 'TRP-60', 'target' => 'p:'.$second->id])->assertSessionHas('error');
        $this->assertNull($second->fresh()->warehouse_sku);

        $this->delete(route('admin.stock.warehouse.unlink'), ['sku' => 'TRP-60'])->assertSessionHas('success');
        $this->assertNull($product->fresh()->warehouse_sku);
    }

    public function test_variant_products_must_be_linked_per_variant(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['has_variants' => true]);
        WarehouseStockItem::create(['sku' => 'WH-1', 'name' => 'Barang', 'qty' => 3, 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.stock.warehouse.link'), ['sku' => 'WH-1', 'target' => 'p:'.$product->id])
            ->assertSessionHas('error');
        $this->assertNull($product->fresh()->warehouse_sku);
    }

    public function test_manual_sync_button_and_access_rules(): void
    {
        $this->fakeWarehouse([$this->row('WH-001', 2)]);
        $warehouseStaff = User::factory()->staff(StaffRole::Warehouse)->create();

        $this->actingAs($warehouseStaff)->get(route('admin.stock.index'))->assertSee(route('admin.stock.warehouse'), false);
        $this->get(route('admin.stock.warehouse'))->assertOk()->assertDontSee(route('admin.integrations.index'), false);
        $this->post(route('admin.stock.warehouse.sync'))->assertSessionHas('success');
        $this->assertSame($warehouseStaff->id, WarehouseSyncRun::sole()->user_id);

        $this->actingAs(User::factory()->staff(StaffRole::CustomerService)->create())
            ->get(route('admin.stock.warehouse'))->assertForbidden();
    }

    public function test_superadmin_saves_settings_with_encrypted_token_and_tests_connection(): void
    {
        config(['services.warehouse.enabled' => false, 'services.warehouse.base_url' => null, 'services.warehouse.token' => null]);
        $this->app->forgetInstance(StoreSettings::class);
        $superadmin = User::factory()->admin()->create();

        $this->actingAs($superadmin)->put(route('admin.integrations.update', 'warehouse'), ['warehouse' => [
            'enabled' => '1', 'base_url' => 'https://gudang.test', 'token' => '', 'interval' => '10',
        ]])->assertSessionHasErrors('warehouse.token');

        $this->put(route('admin.integrations.update', 'warehouse'), ['warehouse' => [
            'enabled' => '1', 'base_url' => 'https://gudang.test', 'token' => 'TOKEN-RAHASIA-123', 'interval' => '10',
        ]])->assertSessionHasNoErrors();

        $this->assertTrue(config('services.warehouse.enabled'));
        $this->assertSame(10, config('services.warehouse.interval'));
        $this->assertSame('TOKEN-RAHASIA-123', config('services.warehouse.token'));
        $this->assertStringNotContainsString('RAHASIA', Setting::find('services.warehouse.token')->value);
        $this->get(route('admin.integrations.index'))->assertOk()->assertDontSee('TOKEN-RAHASIA-123')->assertSee('Stok gudang (warehouse29)');

        Http::fake(['gudang.test/api/v1/health' => Http::response(['success' => true, 'warehouse_code' => 'WSEHA29', 'server_time' => now()->toIso8601String()])]);
        $this->post(route('admin.integrations.test', 'warehouse'))->assertRedirect();
        $this->assertTrue(session('integration_test')['warehouse']['ok']);
        $this->assertStringContainsString('WSEHA29', session('integration_test')['warehouse']['message']);
    }

    public function test_scheduled_command_runs_sync(): void
    {
        $this->fakeWarehouse([]);

        $this->artisan('warehouse:sync-stock --full')->assertSuccessful();
        $this->assertSame('full', WarehouseSyncRun::sole()->mode);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function fakeWarehouse(array $rows): void
    {
        Http::fake(['gudang.test/api/v1/stocks*' => Http::response([
            'success' => true,
            'meta' => ['warehouse_code' => 'WSEHA29', 'server_time' => '2026-10-28T15:00:00+07:00', 'page' => 1, 'per_page' => 500, 'total' => count($rows), 'total_pages' => 1],
            'data' => $rows,
        ])]);
    }

    private function row(string $sku, float $qty, string $name = 'Barang gudang', string $status = 'active'): array
    {
        return [
            'sku' => $sku, 'name' => $name, 'category' => 'Display', 'uom' => 'pcs', 'qty' => $qty,
            'min_qty' => null, 'status' => $status, 'updated_at' => '2026-10-28T14:00:00+07:00',
        ];
    }

    private function orderWith(Product $product, int $quantity, OrderStatus $status): Order
    {
        $order = Order::factory()->status($status)->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 10000,
            'quantity' => $quantity,
            'subtotal' => 10000 * $quantity,
        ]);

        return $order;
    }
}
