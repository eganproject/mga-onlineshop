<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    private function variantPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Casing Clear',
            'weight' => 100,
            'is_active' => 1,
            'has_variants' => 1,
            'variant_label' => 'Warna',
            'variants' => [
                ['name' => 'Hitam', 'sku' => 'CC-HTM', 'price' => '50.000', 'stock' => 5, 'is_active' => 1],
                ['name' => 'Putih', 'sku' => 'CC-PTH', 'price' => '45.000', 'compare_at_price' => '60.000', 'stock' => 3, 'is_active' => 1],
                ['name' => 'Merah', 'price' => '40.000', 'stock' => 9],
            ],
        ], $overrides);
    }

    public function test_admin_creates_product_with_variants_and_summary_is_synced(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->variantPayload())
            ->assertSessionHasNoErrors()->assertRedirect();

        $product = Product::firstWhere('name', 'Casing Clear');
        $this->assertTrue($product->has_variants);
        $this->assertSame('Warna', $product->variant_label);
        $this->assertCount(3, $product->variants);
        // Varian Merah tidak aktif, jadi tidak dihitung.
        $this->assertSame(8, $product->stock);
        $this->assertSame(45000, $product->price);
        $this->assertSame(60000, $product->compare_at_price);
        $this->assertSame(3, StockMovement::where('type', StockMovementType::Initial)->count());
    }

    public function test_variant_validation_rejects_duplicates_and_missing_rows(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.products.store'), $this->variantPayload(['variants' => [
            ['name' => 'Hitam', 'price' => 1000, 'stock' => 1],
            ['name' => 'hitam', 'price' => 1000, 'stock' => 1],
        ]]))->assertSessionHasErrors('variants.1.name');

        $this->post(route('admin.products.store'), $this->variantPayload(['variants' => []]))
            ->assertSessionHasErrors('variants');

        ProductVariant::factory()->create(['sku' => 'DIPAKAI']);
        $this->post(route('admin.products.store'), $this->variantPayload(['variants' => [
            ['name' => 'Hitam', 'sku' => 'DIPAKAI', 'price' => 1000, 'stock' => 1],
        ]]))->assertSessionHasErrors('variants.0.sku');
    }

    public function test_updating_variants_keeps_sales_made_while_form_was_open(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->variantPayload());
        $product = Product::firstWhere('name', 'Casing Clear');
        [$hitam, $putih, $merah] = $product->variants;

        // Terjual 2 Hitam setelah admin membuka form (form masih menampilkan stok 5).
        $hitam->forceFill(['stock' => 3])->save();

        $this->put(route('admin.products.update', $product), $this->variantPayload(['variants' => [
            ['id' => $hitam->id, 'name' => 'Hitam', 'sku' => 'CC-HTM', 'price' => 50000, 'stock' => 5, 'stock_original' => 5, 'is_active' => 1],
            ['id' => $putih->id, 'name' => 'Putih Susu', 'sku' => 'CC-PTH', 'price' => 45000, 'stock' => 10, 'stock_original' => 3, 'is_active' => 1],
            ['name' => 'Biru', 'price' => 55000, 'stock' => 4, 'is_active' => 1],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame(3, $hitam->refresh()->stock);
        $this->assertSame(10, $putih->refresh()->stock);
        $this->assertSame('Putih Susu', $putih->name);
        $this->assertModelMissing($merah);
        $this->assertSame(17, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $putih->id, 'type' => 'adjustment', 'quantity' => 7, 'stock_after' => 10,
        ]);
    }

    public function test_turning_variants_off_uses_product_stock_from_form(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['stock' => 4]);
        $this->assertTrue($product->refresh()->has_variants);

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'name' => $product->name, 'weight' => 100, 'has_variants' => 0,
            'price' => 20000, 'stock' => 4, 'stock_original' => '',
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertFalse($product->has_variants);
        $this->assertSame(0, $product->variants()->count());
        $this->assertSame(4, $product->stock);
        $this->assertSame(20000, $product->price);
    }

    public function test_gallery_can_be_reordered_and_images_removed(): void
    {
        $product = Product::factory()->create(['stock' => 2]);
        $a = ProductImage::factory()->for($product)->create(['sort_order' => 0]);
        $b = ProductImage::factory()->for($product)->create(['sort_order' => 1]);
        $c = ProductImage::factory()->for($product)->create(['sort_order' => 2]);
        Storage::disk('public')->put($a->path, 'x');

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'name' => $product->name, 'weight' => 100, 'price' => 1000, 'stock' => 2, 'stock_original' => 2,
            'image_order' => [$c->id, $a->id, $b->id],
            'remove_images' => [$a->id],
            'images' => [UploadedFile::fake()->image('baru.png')],
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame([$c->id, $b->id], $product->images->take(2)->pluck('id')->all());
        $this->assertCount(3, $product->images);
        $this->assertSame($c->id, $product->coverImage->id);
        $this->assertModelMissing($a);
        Storage::disk('public')->assertMissing($a->path);
        // Stok tidak berubah, jadi tidak ada riwayat baru.
        $this->assertSame(0, StockMovement::count());
    }

    public function test_image_limit_is_enforced(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->count(9)->for($product)->create();

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'name' => $product->name, 'weight' => 100, 'price' => 1000, 'stock' => 1,
            'images' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')],
        ])->assertSessionHasErrors('images');
    }

    public function test_customer_buys_a_variant_and_cancel_restores_stock(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $product = Product::factory()->create(['weight' => 200]);
        $hitam = ProductVariant::factory()->for($product)->create(['name' => 'Hitam', 'price' => 30000, 'stock' => 5, 'weight' => 500]);
        ProductVariant::factory()->for($product)->create(['name' => 'Putih', 'price' => 25000, 'stock' => 2]);

        $this->actingAs($customer);

        // Produk bervarian wajib memilih varian.
        $this->post(route('cart.store', $product), ['quantity' => 1])->assertSessionHas('error');

        $this->post(route('cart.store', $product), ['variant_id' => $hitam->id, 'quantity' => 2])->assertSessionHas('success');
        $this->assertSame(["{$product->id}-{$hitam->id}" => 2], session('cart'));
        $this->get(route('cart.index'))->assertOk()->assertSee('Hitam')->assertSee('Rp60.000');

        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'])->assertRedirect();

        $order = Order::first();
        $item = $order->items->first();
        $this->assertSame('Hitam', $item->variant_name);
        $this->assertSame(30000, $item->price);
        $this->assertSame(500, $item->weight);
        $this->assertSame(60000, $order->subtotal);
        $this->assertSame(3, $hitam->refresh()->stock);
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'type' => 'sale', 'quantity' => -2]);

        app(OrderService::class)->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $hitam->refresh()->stock);
        $this->assertSame(7, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'type' => 'cancellation', 'quantity' => 2]);
    }

    public function test_cart_drops_inactive_variants_and_caps_by_variant_stock(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 2]);

        $this->post(route('cart.store', $product), ['variant_id' => $variant->id, 'quantity' => 9]);
        $this->assertSame(["{$product->id}-{$variant->id}" => 2], session('cart'));

        $this->patch(route('cart.update', "{$product->id}-{$variant->id}"), ['quantity' => 5])->assertSessionHas('error');

        $variant->update(['is_active' => false]);
        $this->get(route('cart.index'))->assertOk()->assertSee('Keranjangmu masih kosong');
        $this->assertSame([], session('cart'));
    }

    public function test_product_page_shows_gallery_and_variants(): void
    {
        $product = Product::factory()->create(['variant_label' => 'Tipe HP']);
        ProductImage::factory()->count(2)->for($product)->create();
        ProductVariant::factory()->for($product)->create(['name' => 'iPhone 15', 'price' => 10000]);
        ProductVariant::factory()->for($product)->create(['name' => 'iPhone 15 Pro', 'price' => 20000, 'stock' => 0]);
        ProductVariant::factory()->for($product)->create(['name' => 'Rahasia', 'is_active' => false]);

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Tipe HP')->assertSee('iPhone 15 Pro')->assertSee('(habis)', false)
            ->assertDontSee('Rahasia')->assertSee('Mulai')->assertSee('Lihat foto 2');
    }

    public function test_admin_can_adjust_stock_and_see_history(): void
    {
        $product = Product::factory()->create(['stock' => 20]);
        $withVariants = Product::factory()->create();
        $variant = ProductVariant::factory()->for($withVariants)->create(['stock' => 1]);

        $this->actingAs($this->admin);
        $this->get(route('admin.stock.index'))->assertOk()->assertSee($product->name)->assertSee($variant->name);
        $this->get(route('admin.stock.index', ['status' => 'menipis']))->assertOk()->assertSee($variant->name)->assertDontSee($product->name);

        $this->post(route('admin.stock.adjust'), [
            'product_id' => $product->id, 'mode' => 'add', 'quantity' => 10, 'type' => 'restock', 'note' => 'Kiriman supplier',
        ])->assertSessionHas('success');
        $this->assertSame(30, $product->refresh()->stock);

        $this->post(route('admin.stock.adjust'), [
            'product_id' => $product->id, 'mode' => 'subtract', 'quantity' => 99, 'type' => 'adjustment',
        ])->assertSessionHas('error');
        $this->assertSame(30, $product->refresh()->stock);

        $this->post(route('admin.stock.adjust'), [
            'product_id' => $withVariants->id, 'variant_id' => $variant->id, 'mode' => 'set', 'quantity' => 8, 'type' => 'adjustment',
        ])->assertSessionHas('success');
        $this->assertSame(8, $variant->refresh()->stock);
        $this->assertSame(8, $withVariants->refresh()->stock);

        // Jenis "penjualan" tidak boleh dipilih manual.
        $this->post(route('admin.stock.adjust'), [
            'product_id' => $product->id, 'mode' => 'add', 'quantity' => 1, 'type' => 'sale',
        ])->assertSessionHasErrors('type');

        $this->get(route('admin.stock.history'))->assertOk()->assertSee('Kiriman supplier')->assertSee('+10');
        $this->get(route('admin.stock.history', ['produk' => $withVariants->id, 'jenis' => 'adjustment']))
            ->assertOk()->assertSee($variant->name)->assertDontSee('Kiriman supplier');
    }

    public function test_subcategories_are_limited_to_two_levels_and_filter_includes_children(): void
    {
        $this->actingAs($this->admin);
        $parent = Category::factory()->create(['name' => 'Casing HP', 'slug' => 'casing-hp']);

        $this->post(route('admin.categories.store'), ['name' => 'Casing iPhone', 'parent_id' => $parent->id, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $child = Category::firstWhere('name', 'Casing iPhone');
        $this->assertTrue($child->parent->is($parent));

        // Anak tidak bisa jadi induk; induk yang punya anak tidak bisa jadi anak.
        $this->post(route('admin.categories.store'), ['name' => 'Level 3', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
        $other = Category::factory()->create();
        $this->put(route('admin.categories.update', $parent), ['name' => 'Casing HP', 'parent_id' => $other->id])->assertSessionHasErrors('parent_id');

        $this->get(route('admin.categories.index'))->assertOk()->assertSeeInOrder(['Casing HP', 'Casing iPhone']);
        $this->patch(route('admin.categories.toggle', $other))->assertRedirect();
        $this->assertFalse($other->refresh()->is_active);

        $inChild = Product::factory()->for($child)->create(['name' => 'Produk Anak']);
        $this->get(route('products.index', ['kategori' => 'casing-hp']))->assertOk()->assertSee($inChild->name)->assertSee('Casing iPhone');
        $this->get(route('home'))->assertOk();
    }

    public function test_bulk_actions_update_selected_products(): void
    {
        $products = Product::factory()->count(3)->create();

        $this->actingAs($this->admin)->post(route('admin.products.bulk'), [
            'action' => 'deactivate', 'ids' => $products->take(2)->modelKeys(),
        ])->assertSessionHas('success');

        $this->assertSame([false, false, true], $products->map(fn ($p) => $p->refresh()->is_active)->all());

        $this->post(route('admin.products.bulk'), ['action' => 'delete', 'ids' => [$products[0]->id]]);
        $this->assertModelMissing($products[0]);
    }

    public function test_admin_product_pages_render_with_variants(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->create();
        ProductVariant::factory()->for($product)->create(['name' => 'Hitam Doff']);

        $this->actingAs($this->admin);
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('Hitam Doff');
        $this->get(route('admin.products.index', ['status' => 'stok-menipis']))->assertOk();
        $this->get(route('admin.products.index'))->assertOk()->assertSee('1 varian');
    }
}
