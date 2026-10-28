<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_has_meta_tags_and_structured_data(): void
    {
        $product = Product::factory()->create([
            'name' => 'Charger GaN 65W',
            'description' => "Charger cepat 3 port.\n\nCocok untuk laptop & HP.",
            'price' => 249000,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('<meta name="description" content="Charger cepat 3 port. Cocok untuk laptop &amp; HP.">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<link rel="canonical" href="'.route('products.show', $product).'">', false)
            ->assertSee('<meta name="robots" content="index, follow', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"price":249000', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_structured_data_escapes_html(): void
    {
        $product = Product::factory()->create(['name' => 'Casing </script><script>alert(1)</script>']);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('</script><script>alert(1)', false);
    }

    public function test_private_and_filtered_pages_are_noindex(): void
    {
        $this->get(route('login'))->assertSee('noindex, follow', false);
        $this->get(route('cart.index'))->assertSee('noindex, follow', false);
        $this->get(route('products.index', ['q' => 'kabel']))->assertSee('noindex, follow', false);
        $this->get(route('products.index'))->assertSee('index, follow, max-image-preview:large', false);

        $this->actingAs(User::factory()->create())->get(route('account.dashboard'))->assertSee('noindex, follow', false);
    }

    public function test_category_page_canonical_keeps_category_only(): void
    {
        $category = Category::factory()->create(['slug' => 'kabel-data', 'description' => 'Kabel data original segala tipe.']);

        $this->get(route('products.index', ['kategori' => 'kabel-data', 'urut' => 'termurah']))
            ->assertSee('<link rel="canonical" href="'.route('products.index', ['kategori' => 'kabel-data']).'">', false)
            ->assertSee('content="Kabel data original segala tipe."', false);
    }

    public function test_home_has_website_schema(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('"@type":"WebSite"', false)->assertSee('"SearchAction"', false);
    }

    public function test_sitemap_lists_active_products_and_categories(): void
    {
        $category = Category::factory()->create(['slug' => 'powerbank']);
        $active = Product::factory()->create(['slug' => 'powerbank-10000']);
        $inactive = Product::factory()->inactive()->create(['slug' => 'produk-lama']);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('products.show', $active), false)
            ->assertSee(e(route('products.index', ['kategori' => 'powerbank'])), false)
            ->assertDontSee('produk-lama');
    }

    public function test_robots_txt(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: '.route('sitemap'));

        config(['shop.seo.indexing' => false]);
        $this->get('/robots.txt')->assertSee('Disallow: /')->assertDontSee('Sitemap:');
        $this->get(route('home'))->assertSee('noindex, follow', false);
    }

    public function test_product_custom_seo_title_and_description(): void
    {
        $product = Product::factory()->create(['name' => 'Tripod Banner', 'description' => 'Deskripsi biasa.']);
        $product->forceFill(['meta_title' => 'Tripod Banner Murah 60x160', 'meta_description' => 'Tripod banner kuat, harga gudang.'])->save();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('<title>Tripod Banner Murah 60x160 · MGA', false)
            ->assertSee('<meta name="description" content="Tripod banner kuat, harga gudang.">', false)
            ->assertSee('"@type":"Brand"', false)
            ->assertSee('<h1', false);
    }

    public function test_admin_can_save_product_and_category_seo_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->get(route('admin.products.create'))->assertOk()->assertSee('Judul SEO');

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'is_active' => 1,
            'description' => 'Semua jenis tripod banner.',
            'meta_title' => 'Tripod Banner Murah',
            'meta_description' => 'Pilihan tripod banner lengkap.',
        ])->assertRedirect();

        $category->refresh();
        $this->assertSame('Tripod Banner Murah', $category->meta_title);

        $this->get(route('products.index', ['kategori' => $category->slug]))
            ->assertOk()
            ->assertSee('<title>Tripod Banner Murah · MGA', false)
            ->assertSee('content="Pilihan tripod banner lengkap."', false)
            ->assertSee('Semua jenis tripod banner.')
            ->assertSee('"@type":"CollectionPage"', false);
    }

    public function test_seo_settings_page_saves_verification_and_analytics(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.seo.edit'))->assertOk()->assertSee('Beranda di Google');

        $this->actingAs($admin)->put(route('admin.seo.update'), [
            'home_title' => 'MGA – Grosir Display Akrilik',
            'description' => 'Display promosi harga gudang.',
            'google_verification' => '<meta name="google-site-verification" content="abc123_XYZ-9" />',
            'bing_verification' => '',
            'analytics_id' => 'g-ab12cd34ef',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>MGA – Grosir Display Akrilik</title>', false)
            ->assertSee('<meta name="description" content="Display promosi harga gudang.">', false)
            ->assertSee('<meta name="google-site-verification" content="abc123_XYZ-9">', false)
            ->assertSee('googletagmanager.com/gtag/js?id=G-AB12CD34EF', false);

        // Panel admin tidak dilacak Analytics.
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('googletagmanager.com', false);
    }

    public function test_seo_settings_reject_invalid_values_and_staff_without_permission(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.seo.update'), ['analytics_id' => 'UA-12345-1'])
            ->assertSessionHasErrors('analytics_id');

        $this->actingAs(User::factory()->staff(StaffRole::Warehouse)->create())
            ->get(route('admin.seo.edit'))->assertForbidden();
    }
}
