<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_url_shows_branded_404_with_search_suggestion(): void
    {
        Category::factory()->create(['name' => 'Frame Akrilik', 'slug' => 'frame-akrilik', 'parent_id' => null]);

        $this->get('/halaman-lama/x-banner-rangka-60x160.html')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('value="x banner rangka 60x160"', false)
            ->assertSee('Frame Akrilik')
            ->assertSee('noindex', false)
            ->assertDontSee('Oops! An Error Occurred');
    }

    public function test_model_not_found_uses_branded_404(): void
    {
        $this->get('/produk/tidak-ada-sama-sekali')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('value="tidak ada sama sekali"', false);
    }

    public function test_old_wordpress_product_url_redirects_to_product(): void
    {
        $product = Product::factory()->create(['slug' => 'tripod-banner-2-sisi']);

        $this->get('/product/tripod-banner-2-sisi/')->assertRedirect(route('products.show', $product))->assertStatus(301);
        $this->get('/tripod-banner-2-sisi/')->assertRedirect(route('products.show', $product))->assertStatus(301);
    }

    public function test_old_wordpress_product_without_match_redirects_to_search(): void
    {
        $this->get('/product/x-banner-lama-60x160/')
            ->assertStatus(301)
            ->assertRedirect(route('products.index', ['q' => 'x banner lama 60x160']));
    }

    public function test_old_wordpress_category_and_shop_urls_redirect(): void
    {
        Category::factory()->create(['slug' => 'frame-akrilik', 'parent_id' => null]);

        $this->get('/product-category/frame-akrilik/page/2/')->assertRedirect(route('products.index', ['kategori' => 'frame-akrilik']));
        $this->get('/shop/')->assertRedirect(route('products.index'));
        $this->get('/cart/')->assertRedirect(route('cart.index'));
    }

    public function test_inactive_product_and_wordpress_internals_are_not_redirected(): void
    {
        Product::factory()->create(['slug' => 'produk-nonaktif', 'is_active' => false]);

        $this->get('/produk-nonaktif')->assertNotFound();
        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/wp-admin/')->assertNotFound();
        $this->get('/2024/05/halo-dunia/')->assertNotFound();
    }

    public function test_product_search_matches_all_words_in_any_order(): void
    {
        Product::factory()->create(['name' => 'X Banner Rangka Standing Poster 60x160 cm']);

        $this->get(route('products.index', ['q' => 'banner x 60x160']))->assertSee('X Banner Rangka Standing Poster 60x160 cm');
        $this->get(route('products.index', ['q' => 'banner tripod']))->assertDontSee('X Banner Rangka Standing Poster 60x160 cm');
    }

    public function test_error_pages_render_without_database(): void
    {
        $default = config('database.default');
        config([
            'database.connections.rusak' => ['driver' => 'sqlite', 'database' => '/path/yang/tidak/ada/db.sqlite', 'prefix' => ''],
            'database.default' => 'rusak',
        ]);

        try {
            foreach ([403 => 'Akses ditolak', 404 => 'Halaman tidak ditemukan', 419 => 'Sesi sudah kedaluwarsa', 429 => 'Terlalu banyak percobaan', 500 => 'Ada kendala di server kami', 503 => 'Toko sedang dalam perawatan'] as $code => $title) {
                $html = view("errors.$code", ['exception' => new HttpException($code)])->render();

                $this->assertStringContainsString("<title>{$title} · ", $html);
            }
        } finally {
            config(['database.default' => $default]);
        }
    }

    public function test_server_error_shows_branded_page(): void
    {
        config(['app.debug' => false]);
        Route::get('/_tes-error', fn () => throw new \RuntimeException('rusak'))->middleware('web');

        $this->get('/_tes-error')
            ->assertStatus(500)
            ->assertSee('Ada kendala di server kami')
            ->assertDontSee('rusak');
    }
}
