<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\StaffRole;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_list_shows_only_published_articles(): void
    {
        Article::factory()->create(['title' => 'Tips Merawat Baterai']);
        Article::factory()->draft()->create(['title' => 'Draf Rahasia']);
        Article::factory()->scheduled()->create(['title' => 'Artikel Minggu Depan']);

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSee('Tips Merawat Baterai')
            ->assertDontSee('Draf Rahasia')
            ->assertDontSee('Artikel Minggu Depan');
    }

    public function test_article_list_filters_by_category_tag_and_search(): void
    {
        $tips = ArticleCategory::factory()->create(['name' => 'Tips', 'slug' => 'tips']);
        Article::factory()->create(['title' => 'Charger Aman', 'article_category_id' => $tips->id, 'tags' => ['charger']]);
        Article::factory()->create(['title' => 'Kabar Toko Baru', 'tags' => ['info']]);

        $this->get(route('articles.index', ['kategori' => 'tips']))->assertSee('Charger Aman')->assertDontSee('Kabar Toko Baru');
        $this->get(route('articles.index', ['tag' => 'info']))->assertSee('Kabar Toko Baru')->assertDontSee('Charger Aman');
        $this->get(route('articles.index', ['q' => 'charger']))->assertSee('Charger Aman')->assertDontSee('Kabar Toko Baru')
            ->assertSee('noindex, follow', false);
    }

    public function test_article_page_has_seo_meta_structured_data_share_buttons_and_related_products(): void
    {
        $product = Product::factory()->create(['name' => 'Charger GaN 65W']);
        $article = Article::factory()->create([
            'title' => 'Memilih Charger yang Tepat',
            'meta_description' => 'Panduan memilih charger cepat & aman.',
            'tags' => ['charger'],
        ]);
        $article->products()->attach($product, ['sort_order' => 0]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta name="description" content="Panduan memilih charger cepat &amp; aman.">', false)
            ->assertSee('<link rel="canonical" href="'.route('articles.show', $article).'">', false)
            ->assertSee('<meta name="robots" content="index, follow', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('https://wa.me/?text=', false)
            ->assertSee('Charger GaN 65W')
            ->assertSee('#charger');
    }

    public function test_drafts_are_hidden_from_customers_but_previewable_by_staff(): void
    {
        $draft = Article::factory()->draft()->create();
        $scheduled = Article::factory()->scheduled()->create();

        $this->get(route('articles.show', $draft))->assertNotFound();
        $this->get(route('articles.show', $scheduled))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('articles.show', $draft))->assertNotFound();
        $this->actingAs(User::factory()->staff(StaffRole::Warehouse)->create())->get(route('articles.show', $draft))->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('articles.show', $draft))
            ->assertOk()
            ->assertSee('Pratinjau.')
            ->assertSee('noindex, follow', false);

        // Artikel terjadwal tayang sendiri setelah waktunya tiba.
        auth()->logout();
        $this->travel(8)->days();
        $this->get(route('articles.show', $scheduled))->assertOk();
    }

    public function test_sitemap_and_homepage_include_published_articles(): void
    {
        $published = Article::factory()->create(['title' => 'Artikel Beranda']);
        $draft = Article::factory()->draft()->create();

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('articles.index'), false)
            ->assertSee(route('articles.show', $published), false)
            ->assertDontSee(route('articles.show', $draft), false);

        $this->get(route('home'))->assertOk()->assertSee('Artikel terbaru')->assertSee('Artikel Beranda');
    }

    public function test_admin_can_write_article_with_sanitized_html_and_related_products(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = ArticleCategory::factory()->create();
        $products = Product::factory()->count(2)->create();
        Article::factory()->create(['slug' => 'tips-casing-hp']);

        $this->actingAs($admin)->post(route('admin.articles.store'), [
            'title' => 'Tips Casing HP',
            'body' => '<h2>Pilih bahan</h2><p onclick="alert(1)">Silikon <strong>empuk</strong><script>alert(1)</script></p><p><a href="javascript:alert(1)">klik</a></p>',
            'status' => 'published',
            'article_category_id' => $category->id,
            'tags' => ['Casing', 'casing', 'tips'],
            'product_ids' => [$products[1]->id, $products[0]->id],
            'cover_image' => UploadedFile::fake()->image('sampul.jpg', 1600, 900),
        ])->assertRedirect()->assertSessionHas('success', 'Artikel diterbitkan.');

        $article = Article::where('title', 'Tips Casing HP')->firstOrFail();
        $this->assertSame('tips-casing-hp-2', $article->slug);
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertTrue($article->isVisible());
        $this->assertTrue($article->author->is($admin));
        $this->assertSame(['casing', 'tips'], $article->tags);
        $this->assertStringNotContainsString('script', $article->body);
        $this->assertStringNotContainsString('onclick', $article->body);
        $this->assertStringNotContainsString('javascript:', $article->body);
        $this->assertStringContainsString('<strong>empuk</strong>', $article->body);
        $this->assertSame([$products[1]->id, $products[0]->id], $article->products->pluck('id')->all());
        Storage::disk('public')->assertExists($article->cover_image_path);
    }

    public function test_future_publish_date_schedules_the_article(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.articles.store'), [
            'title' => 'Promo Akhir Tahun',
            'body' => '<p>Segera hadir.</p>',
            'status' => 'published',
            'published_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success', fn ($message) => str_starts_with($message, 'Artikel dijadwalkan terbit'));

        $article = Article::firstOrFail();
        $this->assertSame('scheduled', $article->state());
        $this->get(route('articles.index'))->assertDontSee('Promo Akhir Tahun');
    }

    public function test_empty_body_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.articles.store'), ['title' => 'Kosong', 'body' => '<p></p>', 'status' => 'draft'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_editor_image_upload_and_delete_cleans_up_files(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        $url = $this->post(route('admin.articles.images'), ['image' => UploadedFile::fake()->image('foto.png', 800, 600)])
            ->assertOk()
            ->json('url');
        $path = Article::CONTENT_IMAGE_DIR.'/'.basename($url);
        Storage::disk('public')->assertExists($path);

        $this->post(route('admin.articles.store'), [
            'title' => 'Artikel Bergambar',
            'body' => '<p>Halo</p><img src="'.$url.'" alt="foto">',
            'status' => 'draft',
        ])->assertSessionHas('success', 'Draf artikel disimpan.');

        $article = Article::firstOrFail();
        $this->assertStringContainsString('<img src="'.$url.'"', $article->body);
        $this->assertSame([$path], $article->contentImagePaths());

        $this->get(route('admin.articles.index'))->assertOk()->assertSee('Artikel Bergambar');
        $this->get(route('admin.articles.edit', $article))->assertOk();

        $this->delete(route('admin.articles.destroy', $article))->assertRedirect(route('admin.articles.index'));
        $this->assertModelMissing($article);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_can_manage_article_categories(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.article-categories.store'), ['name' => 'Review Produk'])->assertRedirect();
        $category = ArticleCategory::where('slug', 'review-produk')->firstOrFail();
        $article = Article::factory()->create(['article_category_id' => $category->id]);

        $this->get(route('admin.article-categories.index'))->assertOk()->assertSee('Review Produk');
        $this->put(route('admin.article-categories.update', $category), ['name' => 'Ulasan Produk', 'slug' => 'ulasan'])->assertRedirect();
        $category->refresh();
        $this->assertSame('ulasan', $category->slug);

        $this->delete(route('admin.article-categories.destroy', $category))->assertRedirect();
        $this->assertNull($article->fresh()->article_category_id);
    }

    public function test_only_staff_with_article_permission_can_manage_articles(): void
    {
        $article = Article::factory()->create();

        foreach ([StaffRole::Warehouse, StaffRole::CustomerService] as $role) {
            $this->actingAs(User::factory()->staff($role)->create());
            $this->get(route('admin.dashboard'))->assertDontSee(route('admin.articles.index'));
            $this->get(route('admin.articles.index'))->assertForbidden();
            $this->post(route('admin.articles.images'))->assertForbidden();
            $this->delete(route('admin.articles.destroy', $article))->assertForbidden();
        }

        $this->actingAs(User::factory()->staff(StaffRole::Admin)->create());
        $this->get(route('admin.dashboard'))->assertSee(route('admin.articles.index'));
        $this->get(route('admin.articles.create'))->assertOk();
        $this->assertModelExists($article);
    }
}
