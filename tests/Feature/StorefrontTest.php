<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Support\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function sell(Product $product, int $qty, OrderStatus $status = OrderStatus::Completed): void
    {
        Order::factory()->status($status)->create()->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'price' => $product->price,
            'quantity' => $qty, 'subtotal' => $product->price * $qty,
        ]);
    }

    public function test_category_tiles_fall_back_to_a_product_photo_and_use_cases_link_to_search(): void
    {
        $parent = Category::factory()->create(['name' => 'Tempat Brosur']);
        $child = Category::factory()->create(['name' => 'Brosur Tempel', 'parent_id' => $parent->id]);
        $product = Product::factory()->create(['category_id' => $child->id, 'is_featured' => true]);
        $image = ProductImage::factory()->create(['product_id' => $product->id]);

        $this->get(route('home'))->assertOk()
            ->assertSee($image->url(), false)
            ->assertSee('Solusi untuk usahamu')
            ->assertSee(route('products.index', ['q' => 'standing sign']), false);
    }

    public function test_homepage_shows_default_hero_tabs_and_announcements(): void
    {
        $category = Category::factory()->create(['name' => 'Casing HP']);
        Product::factory()->create(['name' => 'Kabel Data', 'category_id' => $category->id]);

        $this->get(route('home'))->assertOk()
            ->assertSee(asset(Storefront::DEFAULT_IMAGE), false)
            ->assertSee(asset(Storefront::DEFAULT_IMAGE_MOBILE), false)
            ->assertSee('Bikin promosi tokomu lebih menonjol.')
            ->assertSee('Best Seller')
            ->assertSee('Kategori')
            ->assertSee('Casing HP')
            ->assertSee('Kabel Data')
            ->assertSee('Pesan sebelum 16.00 WIB, dikirim hari ini');
    }

    public function test_best_sellers_are_ordered_by_paid_sales_and_filled_with_other_products(): void
    {
        $slow = Product::factory()->create(['name' => 'Terjual Sedikit']);
        $fast = Product::factory()->create(['name' => 'Terjual Banyak']);
        $cancelled = Product::factory()->create(['name' => 'Batal Semua']);
        Product::factory()->create(['name' => 'Belum Terjual']);
        Product::factory()->inactive()->create(['name' => 'Produk Nonaktif']);

        $this->sell($slow, 2);
        $this->sell($fast, 9);
        $this->sell($cancelled, 50, OrderStatus::Cancelled);
        Cache::flush();

        $this->get(route('home'))->assertOk()
            ->assertSeeInOrder(['Terjual Banyak', 'Terjual Sedikit'])
            ->assertSee('Batal Semua')
            ->assertSee('Belum Terjual')
            ->assertDontSee('Produk Nonaktif');
    }

    public function test_admin_updates_hero_and_announcements(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.storefront.edit'))->assertOk()->assertSee('Foto hero');

        $this->put(route('admin.storefront.update'), ['hero_text' => 'light', 'hero_primary_url' => 'javascript:alert(1)', 'hero_secondary_url' => '//evil.test'])
            ->assertSessionHasErrors(['hero_primary_url', 'hero_secondary_url']);
        $this->put(route('admin.storefront.update'), ['hero_text' => 'light', 'hero_image' => UploadedFile::fake()->image('kecil.jpg', 800, 450)])
            ->assertSessionHasErrors('hero_image');

        $this->put(route('admin.storefront.update'), [
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
            'hero_image_mobile' => UploadedFile::fake()->image('hero-hp.jpg', 900, 1600),
            'hero_text' => 'dark',
            'hero_title' => 'Casing baru tiap minggu',
            'hero_primary_label' => 'Belanja casing',
            'hero_primary_url' => '/produk?kategori=casing',
            'announcements' => "Gratis ongkir Jabodetabek\n\n  Diskon 10% member baru  ",
        ])->assertSessionHasNoErrors();

        $image = config('shop.storefront.hero_image');
        Storage::disk('public')->assertExists([$image, config('shop.storefront.hero_image_mobile')]);
        $this->assertSame(['Gratis ongkir Jabodetabek', 'Diskon 10% member baru'], config('shop.storefront.announcements'));

        $this->get(route('home'))->assertOk()
            ->assertSee(Storage::disk('public')->url($image), false)
            ->assertDontSee(asset(Storefront::DEFAULT_IMAGE), false)
            ->assertSee('Casing baru tiap minggu')
            ->assertSee(url('/produk?kategori=casing'), false)
            ->assertSee('Diskon 10% member baru');

        // Hapus foto: kembali ke ilustrasi bawaan dan file lama dibersihkan.
        $this->put(route('admin.storefront.update'), ['hero_text' => 'light', 'remove_hero_image' => 1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($image);
        $this->assertNull(config('shop.storefront.hero_image'));
        $this->assertSame('Bikin promosi tokomu lebih menonjol.', config('shop.storefront.hero_title'));
    }

    public function test_storefront_page_requires_promo_permission(): void
    {
        $this->actingAs(User::factory()->staff(StaffRole::Warehouse)->create())
            ->get(route('admin.storefront.edit'))->assertForbidden();
    }

    public function test_admin_picks_and_orders_best_sellers_manually(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Product::factory()->create(['name' => 'Pilihan Pertama']);
        $second = Product::factory()->create(['name' => 'Pilihan Kedua']);
        $inactive = Product::factory()->inactive()->create(['name' => 'Pilihan Nonaktif']);
        $empty = Product::factory()->outOfStock()->create(['name' => 'Pilihan Habis']);
        $topSeller = Product::factory()->create(['name' => 'Laris Otomatis']);
        $this->sell($topSeller, 30);
        Cache::flush();

        $this->actingAs($admin)->put(route('admin.storefront.best-sellers'), [
            'best_seller_mode' => 'manual', 'best_seller_fill' => '0', 'best_seller_products' => [],
        ])->assertSessionHasErrors('best_seller_products');

        $this->put(route('admin.storefront.best-sellers'), [
            'best_seller_mode' => 'manual',
            'best_seller_fill' => '0',
            'best_seller_products' => [$second->id, $inactive->id, $empty->id, $first->id],
        ])->assertRedirect(route('admin.storefront.edit').'#best-seller')->assertSessionHasNoErrors();

        $this->assertSame([$second->id, $inactive->id, $empty->id, $first->id], config('shop.storefront.best_seller_products'));

        // Halaman admin menampilkan pilihan beserta statusnya.
        $this->get(route('admin.storefront.edit'))->assertOk()
            ->assertSee('Pilihan Kedua')->assertSee('Pilihan Nonaktif');

        auth()->logout();
        $this->get(route('home'))->assertOk()
            ->assertSeeInOrder(['Pilihan Kedua', 'Pilihan Pertama'])
            ->assertDontSee('Pilihan Nonaktif')
            ->assertDontSee('Pilihan Habis')
            ->assertDontSee('Laris Otomatis');

        // Lengkapi otomatis: pilihan manual dulu, lalu terlaris.
        $this->actingAs($admin)->put(route('admin.storefront.best-sellers'), [
            'best_seller_mode' => 'manual', 'best_seller_fill' => '1', 'best_seller_products' => [$first->id, $second->id],
        ])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('home'))->assertOk()->assertSeeInOrder(['Pilihan Pertama', 'Pilihan Kedua', 'Laris Otomatis']);

        // Kembali ke otomatis: daftar manual tetap tersimpan, beranda mengikuti penjualan.
        $this->actingAs($admin)->put(route('admin.storefront.best-sellers'), [
            'best_seller_mode' => 'auto', 'best_seller_fill' => '1', 'best_seller_products' => [$first->id, $second->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$first->id, $second->id], config('shop.storefront.best_seller_products'));
        auth()->logout();
        $this->get(route('home'))->assertOk()->assertSeeInOrder(['Laris Otomatis', 'Pilihan']);
    }

    public function test_best_seller_product_search_returns_json(): void
    {
        Product::factory()->create(['name' => 'Casing Bening', 'sku' => 'CS-01']);
        Product::factory()->create(['name' => 'Kabel Type-C']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.storefront.products', ['q' => 'casing']))
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Casing Bening')
            ->assertJsonStructure([['id', 'name', 'sku', 'image', 'price', 'stock', 'active']]);

        $this->actingAs(User::factory()->staff(StaffRole::Warehouse)->create())
            ->getJson(route('admin.storefront.products'))->assertForbidden();
    }
}
