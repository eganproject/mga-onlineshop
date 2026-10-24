<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\Catalog\MgaCatalog;
use App\Services\Catalog\Transfer\ImageDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MgaCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_file_is_consistent(): void
    {
        $data = app(MgaCatalog::class)->data();
        $categorySlugs = collect($data['categories'])
            ->flatMap(fn ($c) => [$c['slug'], ...array_column($c['children'] ?? [], 'slug')]);

        $slugs = array_column($data['products'], 'slug');
        $this->assertSame(count($slugs), count(array_unique($slugs)), 'Slug produk harus unik.');

        foreach ($data['products'] as $item) {
            $this->assertContains($item['category'], $categorySlugs, "Kategori {$item['category']} untuk {$item['slug']} tidak ada.");
            $this->assertIsInt($item['price']);
            $this->assertGreaterThan(0, $item['weight'] ?? 1);

            foreach ($item['variants'] ?? [] as $variant) {
                $this->assertIsString($variant[0]);
                $this->assertIsInt($variant[1]);
            }

            foreach ($item['images'] as $image) {
                $this->assertDoesNotMatchRegularExpression('/\s/', $image);
            }
        }
    }

    public function test_import_creates_categories_products_variants_and_stock(): void
    {
        $result = app(MgaCatalog::class)->import(initialStock: 25);
        $total = count(app(MgaCatalog::class)->slugs());

        $this->assertSame($total, $result['created']);
        $this->assertSame($total, Product::count());
        $this->assertSame(12, Category::count());
        $this->assertSame(7, Category::whereNull('parent_id')->count());

        $xBanner = Product::firstWhere('slug', 'x-banner-rangka-standing-poster-60x160');
        $this->assertTrue($xBanner->is_active);
        $this->assertTrue($xBanner->is_featured);
        $this->assertSame(15000, $xBanner->price);
        $this->assertSame(29000, $xBanner->compare_at_price);
        $this->assertSame(25, $xBanner->stock);
        $this->assertSame('tripod-stand-banner', $xBanner->category->slug);
        $this->assertStringContainsString('Spesifikasi:', $xBanner->description);
        $this->assertSame(1, $xBanner->stockMovements()->count());

        // Varian: harga produk = varian aktif termurah, varian tanpa harga nonaktif.
        $frame = Product::firstWhere('slug', 'frame-akrilik-f4');
        $this->assertTrue($frame->has_variants);
        $this->assertSame(57000, $frame->price);
        $this->assertSame(4, $frame->variants()->count());
        $this->assertSame(2, $frame->activeVariants()->count());
        $this->assertSame(50, $frame->stock);

        $pocket = Product::firstWhere('slug', 'brosur-tempel-a3');
        $this->assertSame('tempat-brosur', $pocket->category->parent->slug);

        // Produk tanpa harga masuk nonaktif tanpa stok.
        $signage = Product::firstWhere('slug', 'fortu-digital-signage-55-inch');
        $this->assertFalse($signage->is_active);
        $this->assertSame(0, $signage->stock);
        $this->assertStringContainsString('Harga menyusul', $signage->description);
    }

    public function test_import_does_not_overwrite_existing_products(): void
    {
        $catalog = app(MgaCatalog::class);
        $catalog->import();

        $product = Product::firstWhere('slug', 'standing-sign-a4');
        $product->update(['price' => 80000, 'name' => 'Standing Sign A4 Edisi Admin']);

        $result = app(MgaCatalog::class)->import();

        $this->assertSame(0, $result['created']);
        $this->assertSame(count($catalog->slugs()), $result['skipped']);
        $this->assertSame(80000, $product->fresh()->price);
        $this->assertSame('Standing Sign A4 Edisi Admin', $product->fresh()->name);
    }

    public function test_images_are_downloaded_only_with_the_images_option(): void
    {
        $withImages = collect(app(MgaCatalog::class)->data()['products'])->filter(fn ($p) => $p['images'])->count();

        $downloader = Mockery::mock(ImageDownloader::class);
        $downloader->shouldReceive('attach')->times($withImages)
            ->withArgs(fn (Product $product, array $urls) => str_starts_with($urls[0], 'https://mga-advertising.co.id/wp-content/uploads/'))
            ->andReturn([]);
        $this->app->instance(ImageDownloader::class, $downloader);

        app(MgaCatalog::class)->import(withImages: false);
        $result = app(MgaCatalog::class)->import(withImages: true);

        $this->assertGreaterThan(0, $result['images']);
    }

    public function test_command_replaces_demo_catalog_but_keeps_ordered_products(): void
    {
        $demoCategory = Category::factory()->create(['name' => 'Casing HP', 'slug' => 'casing-hp']);
        $unused = Product::factory()->create(['category_id' => $demoCategory->id, 'slug' => 'casing-lama']);
        $ordered = Product::factory()->create(['slug' => 'charger-lama', 'is_active' => true, 'is_featured' => true]);
        Order::factory()->create()->items()->create([
            'product_id' => $ordered->id,
            'product_name' => $ordered->name,
            'price' => 10000,
            'quantity' => 1,
            'subtotal' => 10000,
        ]);

        $this->artisan('shop:import-mga', ['--replace' => true, '--force' => true])->assertSuccessful();

        $this->assertModelMissing($unused);
        $this->assertModelMissing($demoCategory);
        $this->assertFalse($ordered->fresh()->is_active);
        $this->assertFalse($ordered->fresh()->is_featured);
        $this->assertTrue(Product::where('slug', 'tripod-banner-2-sisi')->exists());
    }

    public function test_replace_asks_for_confirmation(): void
    {
        $product = Product::factory()->create(['slug' => 'casing-lama']);

        $this->artisan('shop:import-mga', ['--replace' => true])
            ->expectsConfirmation('Produk & kategori yang bukan katalog MGA akan dihapus (yang pernah dipesan hanya dinonaktifkan). Lanjutkan?', 'no')
            ->assertFailed();

        $this->assertModelExists($product);
        $this->assertSame(1, Product::count());
    }
}
