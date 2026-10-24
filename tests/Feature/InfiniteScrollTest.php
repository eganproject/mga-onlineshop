<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfiniteScrollTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['X-Infinite-Scroll' => '1'];

    public function test_product_list_renders_first_batch_with_infinite_list_and_page_links(): void
    {
        Product::factory()->count(15)->create();

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('data-infinite', false)
            ->assertSee('data-infinite-next="'.route('products.index', ['page' => 2]).'"', false)
            ->assertSee('Muat lebih banyak')
            // Link halaman biasa tetap ada untuk SEO / tanpa JS.
            ->assertSee('rel="next"', false)
            ->assertSee('Menampilkan 1–12 dari 15');
    }

    public function test_next_page_request_returns_only_the_list_fragment(): void
    {
        foreach (range(1, 15) as $i) {
            Product::factory()->create(['name' => sprintf('Produk Urut %02d', $i)]);
        }

        $response = $this->withHeaders(self::HEADER)
            ->get(route('products.index', ['urut' => 'nama', 'page' => 2]))
            ->assertOk()
            ->assertHeader('Vary', 'X-Infinite-Scroll')
            ->assertSee('Produk Urut 13')
            ->assertSee('Produk Urut 15')
            ->assertDontSee('Produk Urut 12')
            ->assertSee('data-infinite-next=""', false);

        // Tanpa layout: tidak ada <html>, header, maupun footer.
        $this->assertStringNotContainsString('<html', $response->getContent());
        $this->assertStringNotContainsString('<footer', $response->getContent());
    }

    public function test_filters_are_kept_in_next_page_url(): void
    {
        Product::factory()->count(14)->create(['price' => 75000]);

        $this->get(route('products.index', ['harga_min' => 50000, 'urut' => 'termurah']))
            ->assertOk()
            ->assertSee('data-infinite-next="'.e(route('products.index', ['harga_min' => 50000, 'urut' => 'termurah', 'page' => 2])).'"', false);
    }

    public function test_single_page_list_has_no_loader(): void
    {
        Product::factory()->count(3)->create();

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('data-infinite-next=""', false)
            ->assertDontSee('Muat lebih banyak');
    }

    public function test_articles_orders_and_wishlist_support_fragments(): void
    {
        Article::factory()->count(12)->create();
        $this->withHeaders(self::HEADER)->get(route('articles.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('data-infinite-items', false)
            ->assertDontSee('<footer', false);

        $customer = User::factory()->create();
        Order::factory()->count(12)->create(['user_id' => $customer->id]);
        $this->actingAs($customer)->get(route('account.orders.index'))
            ->assertOk()
            ->assertSee('Muat lebih banyak');
        $this->actingAs($customer)->withHeaders(self::HEADER)->get(route('account.orders.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('data-infinite-items', false)
            ->assertDontSee('<footer', false);

        $customer->wishlist()->attach(Product::factory()->count(14)->create());
        $this->actingAs($customer)->withHeaders(self::HEADER)->get(route('account.wishlist', ['page' => 2]))
            ->assertOk()
            ->assertSee('data-infinite-items', false);
    }

    public function test_pagination_view_is_the_shop_style(): void
    {
        Product::factory()->count(30)->create();

        $this->get(route('products.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('aria-label="Navigasi halaman"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Menampilkan 13–24 dari 30');
    }

    public function test_product_gallery_has_vertical_thumbnails_and_zoom(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->count(3)->create(['product_id' => $product->id]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('lg:grid-cols-[72px_minmax(0,1fr)]', false)
            ->assertSee('openLightbox(0)', false)
            ->assertSee('zoomMove($event)', false)
            ->assertSee('Perbesar foto 3 dari 3');
    }
}
