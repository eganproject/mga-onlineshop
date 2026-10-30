<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Catalog\Transfer\ProductExporter;
use App\Services\Catalog\Wholesale;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use App\Services\Settings\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleTest extends TestCase
{
    use RefreshDatabase;

    private const TIERS = [['min_qty' => 10, 'price' => 65000], ['min_qty' => 50, 'price' => 60000]];

    private User $customer;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
        $this->address = Address::factory()->for($this->customer)->create();
    }

    private function wholesaleProduct(array $attributes = []): Product
    {
        return Product::factory()->create(['price' => 70000, 'stock' => 200, 'weight' => 500, 'wholesale_tiers' => self::TIERS, ...$attributes]);
    }

    /** Produk bervarian (Hitam & Putih) yang masing-masing punya tingkat grosir. */
    private function variantProduct(): array
    {
        $product = Product::factory()->create(['price' => 70000, 'stock' => 0, 'weight' => 500]);
        $black = ProductVariant::factory()->for($product)->create(['name' => 'Hitam', 'price' => 70000, 'stock' => 100, 'wholesale_tiers' => self::TIERS]);
        $white = ProductVariant::factory()->for($product)->create(['name' => 'Putih', 'price' => 72000, 'stock' => 100, 'wholesale_tiers' => [['min_qty' => 10, 'price' => 66000]]]);

        return [$product->refresh(), $black, $white];
    }

    private function placeOrder()
    {
        return $this->post(route('checkout.store'), ['address_id' => $this->address->id, 'shipping' => 'jne|REG']);
    }

    public function test_tiers_are_normalized_and_summarized(): void
    {
        $this->assertSame(
            [['min_qty' => 10, 'price' => 65000], ['min_qty' => 50, 'price' => 60000]],
            Wholesale::normalize([['min_qty' => 50, 'price' => 60000], ['min_qty' => 1, 'price' => 1], ['min_qty' => 10, 'price' => 65000], ['min_qty' => 20, 'price' => 69000], ['min_qty' => 5, 'price' => 80000]], 70000),
        );

        $product = $this->wholesaleProduct();
        $this->assertSame(10, $product->wholesale_min_qty);
        $this->assertTrue($product->hasWholesale());

        $product->update(['wholesale_tiers' => []]);
        $this->assertNull($product->refresh()->wholesale_min_qty);

        [$variantProduct] = $this->variantProduct();
        $this->assertSame(10, $variantProduct->wholesale_min_qty);

        $this->assertSame([['min_qty' => 10, 'price' => 65000], ['min_qty' => 50, 'price' => 60000]], Wholesale::fromText('10=65.000; 50 pcs: Rp60.000'));
        $this->assertNull(Wholesale::fromText('sepuluh'));
        $this->assertSame('10=65000; 50=60000', Wholesale::toText(self::TIERS));
    }

    public function test_cart_and_order_use_tier_price_by_quantity(): void
    {
        $product = $this->wholesaleProduct();
        $this->actingAs($this->customer)->post(route('cart.store', $product), ['quantity' => 9]);

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Tambah 1 pcs lagi, jadi Rp65.000/pcs', false);

        $this->postJson(route('cart.store', $product), ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('message', '1 pcs '.$product->name.' ditambahkan ke keranjang. Total di keranjang sekarang 10 pcs. Harga grosir Rp65.000/pcs berlaku.');

        $this->get(route('cart.index'))->assertOk()->assertSee('Harga grosir')->assertSee('Hemat harga grosir')->assertSee('Rp650.000');

        $this->placeOrder()->assertSessionHasNoErrors();

        $order = Order::with('items')->first();
        $item = $order->items->first();
        $this->assertSame(65000, $item->price);
        $this->assertSame(70000, $item->regular_price);
        $this->assertSame(650000, $order->subtotal);
        $this->assertTrue($item->isWholesale());
        $this->assertSame(50000, $item->wholesaleSavings());

        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('Harga grosir');
        $order->forceFill(['status' => OrderStatus::Processing, 'paid_at' => now()])->save();
        $this->get(route('account.orders.invoice', $order))->assertOk()->assertSee('Termasuk hemat harga grosir')->assertSee('Grosir · normal', false);
        $this->get(route('account.orders.invoice.pdf', $order))->assertOk();
    }

    public function test_variant_quantities_are_combined_for_tiers(): void
    {
        [$product, $black, $white] = $this->variantProduct();
        $this->actingAs($this->customer);
        $this->post(route('cart.store', $product), ['variant_id' => $black->id, 'quantity' => 6]);
        $this->post(route('cart.store', $product), ['variant_id' => $white->id, 'quantity' => 4]);

        $this->placeOrder()->assertSessionHasNoErrors();

        $items = Order::first()->items->keyBy('variant_name');
        $this->assertSame(65000, $items['Hitam']->price);
        $this->assertSame(66000, $items['Putih']->price);
        $this->assertSame(6 * 65000 + 4 * 66000, Order::first()->subtotal);
    }

    public function test_minimum_order_is_enforced(): void
    {
        $simple = $this->wholesaleProduct(['min_order_qty' => 5]);
        $this->actingAs($this->customer)->post(route('cart.store', $simple), ['quantity' => 1]);
        $this->assertSame(5, session('cart')[(string) $simple->id], 'Produk tanpa varian dibulatkan ke minimal pembelian.');

        // Produk bervarian: boleh campur varian, keranjang menahan checkout bila kurang.
        [$product, $black] = $this->variantProduct();
        $product->forceFill(['min_order_qty' => 10])->save();
        $this->post(route('cart.store', $product), ['variant_id' => $black->id, 'quantity' => 3]);

        $this->get(route('cart.index'))->assertOk()->assertSee('Minimal pembelian '.$product->name.' 10 pcs', false);
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
        $this->placeOrder()->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
    }

    public function test_voucher_setting_controls_wholesale_lines(): void
    {
        Voucher::factory()->percent(10)->create(['code' => 'HEMAT10']);
        $wholesale = $this->wholesaleProduct();
        $retail = Product::factory()->create(['price' => 100000, 'stock' => 20, 'weight' => 300]);

        $this->actingAs($this->customer)->post(route('cart.store', $wholesale), ['quantity' => 10]); // 650.000 grosir
        $this->post(route('cart.store', $retail), ['quantity' => 1]); // 100.000 normal

        // Default: voucher boleh digabung dengan harga grosir.
        $this->postJson(route('checkout.voucher.apply'), ['code' => 'HEMAT10'])->assertOk()->assertJsonPath('voucher.discount', 75000);

        app(StoreSettings::class)->save(['shop.wholesale.voucher_combinable' => false]);
        $this->postJson(route('checkout.voucher.apply'), ['code' => 'HEMAT10'])->assertOk()->assertJsonPath('voucher.discount', 10000);
        $this->get(route('checkout.index'))->assertOk()->assertSee('Produk berharga grosir tidak ikut dihitung voucher');

        $this->placeOrder()->assertSessionHasNoErrors();
        $this->assertSame(10000, Order::first()->discount);

        // Semua barang berharga grosir → voucher ditolak.
        $this->post(route('cart.store', $wholesale), ['quantity' => 10]);
        $this->postJson(route('checkout.voucher.apply'), ['code' => 'HEMAT10'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Voucher tidak berlaku untuk produk dengan harga grosir.');
    }

    public function test_product_page_and_catalog_show_wholesale(): void
    {
        $product = $this->wholesaleProduct(['name' => 'Tripod Banner Grosir']);
        $plain = Product::factory()->create(['name' => 'Frame Eceran']);

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('data-wholesale-table', false)
            ->assertSee('Harga grosir');

        $this->get(route('products.index', ['grosir' => 1]))->assertOk()
            ->assertSee('Tripod Banner Grosir')
            ->assertDontSee('Frame Eceran')
            ->assertSee('Bisa grosir');
    }

    public function test_admin_saves_tiers_and_minimum_from_product_form(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = [
            'name' => 'Tripod 1 Sisi', 'weight' => 1500, 'is_active' => 1, 'has_variants' => 0,
            'price' => '70.000', 'stock' => 30, 'min_order_qty' => 2,
            'wholesale_tiers' => [['min_qty' => '50', 'price' => '60.000'], ['min_qty' => '', 'price' => ''], ['min_qty' => '10', 'price' => '65.000']],
        ];

        // Urutan salah (50 sebelum 10) ditolak.
        $this->actingAs($admin)->post(route('admin.products.store'), $payload)->assertSessionHasErrors('wholesale_tiers');

        $payload['wholesale_tiers'] = [['min_qty' => '10', 'price' => '65.000'], ['min_qty' => '50', 'price' => '60.000']];
        $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();

        $product = Product::firstWhere('name', 'Tripod 1 Sisi');
        $this->assertSame(self::TIERS, $product->wholesale_tiers);
        $this->assertSame(2, $product->min_order_qty);
        $this->assertSame(10, $product->wholesale_min_qty);
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('Harga grosir & minimal pembelian', false);

        // Harga grosir lebih mahal dari harga normal ditolak (varian).
        $this->post(route('admin.products.store'), [
            'name' => 'Frame', 'weight' => 100, 'has_variants' => 1, 'min_order_qty' => 1,
            'variants' => [['name' => 'A4', 'price' => '50.000', 'stock' => 3, 'is_active' => 1, 'wholesale_tiers' => [['min_qty' => 10, 'price' => '55.000']]]],
        ])->assertSessionHasErrors('variants.0.wholesale_tiers');
    }

    public function test_export_and_import_wholesale_column(): void
    {
        $product = $this->wholesaleProduct(['slug' => 'tripod-grosir', 'min_order_qty' => 3]);
        $rows = iterator_to_array(app(ProductExporter::class)->products(Product::query())['Produk'], false);

        $header = $rows[0];
        $row = array_combine($header, $rows[1]);
        $this->assertSame('10=65000; 50=60000', $row['harga_grosir']);
        $this->assertSame(3, $row['minimal_beli']);
    }

    public function test_report_shows_wholesale_breakdown(): void
    {
        $product = $this->wholesaleProduct();
        $this->actingAs($this->customer)->post(route('cart.store', $product), ['quantity' => 10]);
        $this->placeOrder();
        Order::first()->forceFill(['status' => OrderStatus::Processing, 'paid_at' => now()])->save();

        $breakdown = app(SalesReportService::class)->wholesaleBreakdown(ReportPeriod::fromPreset('30d'));
        $this->assertSame(10, $breakdown['wholesale']['quantity']);
        $this->assertSame(50000, $breakdown['wholesale']['savings']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee('Grosir vs eceran');
    }
}
