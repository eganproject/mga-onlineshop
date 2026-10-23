<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Catalog\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Audit panel admin: pagination, urutan, filter, konfirmasi, dan akses per jabatan. */
class AdminListsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->admin()->create();
    }

    public function test_per_page_choice_is_applied_remembered_and_validated(): void
    {
        Product::factory()->count(30)->create();
        $this->actingAs($this->owner);

        $this->get(route('admin.products.index', ['per_page' => 10]))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->perPage() === 10 && $p->count() === 10)
            ->assertSee('Baris per halaman')
            ->assertSee('dari <span class="font-semibold text-stone-800">30</span> produk', false);

        // Diingat per daftar saat parameter tidak dikirim lagi...
        $this->get(route('admin.products.index'))->assertViewHas('products', fn ($p) => $p->perPage() === 10);
        // ...tapi tidak memengaruhi daftar lain.
        $this->get(route('admin.customers.index'))->assertViewHas('customers', fn ($p) => $p->perPage() === 15);

        // Nilai di luar pilihan diabaikan.
        $this->get(route('admin.products.index', ['per_page' => 5000]))->assertViewHas('products', fn ($p) => $p->perPage() === 10);
    }

    public function test_pagination_links_keep_filters_and_page_past_the_end_shows_last_page(): void
    {
        Product::factory()->count(20)->create(['is_active' => true]);
        $this->actingAs($this->owner);

        $response = $this->get(route('admin.products.index', ['status' => 'aktif', 'per_page' => 10]))->assertOk();
        $response->assertSee('status=aktif&amp;per_page=10&amp;page=2', false);

        // Mis. setelah menghapus data terakhir di halaman terakhir.
        $this->get(route('admin.products.index', ['page' => 9]))
            ->assertViewHas('products', fn ($p) => $p->currentPage() === 2 && $p->count() === 10);
    }

    public function test_products_can_be_sorted_by_column_and_invalid_sort_falls_back(): void
    {
        Product::factory()->create(['name' => 'Murah', 'price' => 10000]);
        Product::factory()->create(['name' => 'Mahal', 'price' => 90000]);
        $this->actingAs($this->owner);

        $this->get(route('admin.products.index', ['sort' => 'harga']))
            ->assertSeeInOrder(['Murah', 'Mahal']);
        $this->get(route('admin.products.index', ['sort' => '-harga']))
            ->assertSeeInOrder(['Mahal', 'Murah'])
            ->assertSee('sort=harga', false);

        $this->get(route('admin.products.index', ['sort' => 'password']))->assertOk();
    }

    public function test_lists_that_had_no_pagination_are_paginated(): void
    {
        Banner::factory()->count(20)->create();
        Category::factory()->count(20)->create();
        ArticleCategory::factory()->count(30)->create();
        $this->actingAs($this->owner);

        $this->get(route('admin.banners.index'))->assertOk()
            ->assertViewHas('banners', fn ($p) => $p->total() === 20 && $p->count() === 15);
        $this->get(route('admin.categories.index'))->assertOk()
            ->assertViewHas('paginator', fn ($p) => $p->total() === 20 && $p->count() === 15);
        $this->get(route('admin.article-categories.index'))->assertOk()
            ->assertViewHas('categories', fn ($p) => $p->total() === 30 && $p->count() === 25);
        $this->get(route('admin.products.transfer'))->assertOk()
            ->assertViewHas('imports', fn ($p) => $p->perPage() === 10);
    }

    public function test_banner_list_can_be_searched_and_filtered(): void
    {
        Banner::factory()->create(['title' => 'Promo Gajian', 'is_active' => true]);
        Banner::factory()->create(['title' => 'Promo Lama', 'is_active' => false]);
        $this->actingAs($this->owner);

        $this->get(route('admin.banners.index', ['status' => 'inactive']))
            ->assertSee('Promo Lama')->assertDontSee('Promo Gajian');
        $this->get(route('admin.banners.index', ['q' => 'gajian']))
            ->assertSee('Promo Gajian')->assertDontSee('Promo Lama');
        $this->get(route('admin.banners.index', ['q' => 'tidak-ada']))
            ->assertSee('Banner tidak ditemukan');
    }

    public function test_customer_list_filters_by_status_and_shows_search_empty_state(): void
    {
        User::factory()->create(['name' => 'Pelanggan Aktif']);
        User::factory()->inactive()->create(['name' => 'Pelanggan Diblokir']);
        $this->actingAs($this->owner);

        $this->get(route('admin.customers.index', ['status' => 'diblokir']))
            ->assertSee('Pelanggan Diblokir')->assertDontSee('Pelanggan Aktif');
        $this->get(route('admin.customers.index', ['q' => 'zzz']))
            ->assertSee('Pelanggan tidak ditemukan')->assertDontSee('Belum ada pelanggan');
    }

    public function test_stock_history_can_be_searched_and_filtered_by_date(): void
    {
        $product = Product::factory()->create(['name' => 'Kabel Cari']);
        $other = Product::factory()->create(['name' => 'Charger Lain']);
        $stock = app(StockService::class);
        $this->actingAs($this->owner);
        $stock->adjust($product, null, 5, StockMovementType::Restock);
        $stock->adjust($other, null, 5, StockMovementType::Restock);

        $this->get(route('admin.stock.history', ['q' => 'kabel']))
            ->assertSee('Kabel Cari')->assertDontSee('Charger Lain');
        $this->get(route('admin.stock.history', ['from' => now()->addDay()->toDateString()]))
            ->assertSee('Tidak ada riwayat yang cocok');
    }

    public function test_stock_summary_counts_variants_that_are_out_of_stock(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductVariant::factory()->for($product)->create(['stock' => 40]);
        ProductVariant::factory()->for($product)->create(['stock' => 0]);
        $this->assertSame(40, $product->refresh()->stock);

        $this->actingAs($this->owner)->get(route('admin.stock.index'))
            ->assertViewHas('summary', fn ($s) => $s['out'] === 1 && $s['low'] === 1);
    }

    public function test_warehouse_staff_see_no_links_they_cannot_open(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->create();
        $this->actingAs(User::factory()->staff(StaffRole::Warehouse)->create());

        $this->get(route('admin.stock.index'))->assertOk()
            ->assertSee($product->name)
            ->assertDontSee(route('admin.products.edit', $product));
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee($order->user->name)
            ->assertDontSee(route('admin.customers.show', $order->user));
    }

    public function test_owner_still_gets_the_links(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->create();
        $this->actingAs($this->owner);

        $this->get(route('admin.stock.index'))->assertSee(route('admin.products.edit', $product));
        $this->get(route('admin.orders.show', $order))->assertSee(route('admin.customers.show', $order->user));
    }

    public function test_destructive_actions_use_the_confirmation_modal_not_native_confirm(): void
    {
        $product = Product::factory()->create(['name' => 'Kabel & Charger']);
        $this->actingAs($this->owner);

        $html = $this->get(route('admin.products.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('confirm(', $html);
        $this->assertStringContainsString('data-confirm="Kabel &amp; Charger beserta foto', $html);
        $this->assertStringContainsString('role="alertdialog"', $html);

        $this->get(route('admin.products.edit', $product))->assertOk()->assertDontSee('return confirm(', false);
        $this->get(route('admin.orders.show', Order::factory()->create()))->assertOk()->assertDontSee('confirm(', false);
    }

    public function test_flash_messages_and_validation_errors_become_toasts(): void
    {
        $this->actingAs($this->owner);

        $this->withSession(['success' => 'Produk dihapus.'])->get(route('admin.dashboard'))
            ->assertSee('Produk dihapus.');
    }

    public function test_validation_errors_show_a_toast_summary(): void
    {
        $this->actingAs($this->owner)
            ->from(route('admin.vouchers.create'))
            ->followingRedirects()
            ->post(route('admin.vouchers.store'), [])
            ->assertSee('isian yang perlu diperbaiki');
    }

    public function test_sidebar_collapsed_state_is_read_from_cookie(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('admin.dashboard'))->assertSee('Ciutkan menu')->assertSee('lg:w-72');
        $this->withUnencryptedCookie('admin_sidebar', 'collapsed')->get(route('admin.dashboard'))
            ->assertSee('collapsed: true', false);
    }

    public function test_dashboard_skips_sales_numbers_for_staff_without_report_access(): void
    {
        $this->actingAs(User::factory()->staff(StaffRole::CustomerService)->create())
            ->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('summary', null)
            ->assertDontSee('Pendapatan');
    }

    public function test_activity_log_can_filter_new_menus(): void
    {
        ActivityLog::create(['user_id' => $this->owner->id, 'action' => 'articles.store', 'description' => 'Menulis artikel', 'subject_label' => 'Tips baterai']);
        ActivityLog::create(['user_id' => $this->owner->id, 'action' => 'products.store', 'description' => 'Menambah produk', 'subject_label' => 'Kabel']);

        $this->actingAs($this->owner)->get(route('admin.activity.index', ['grup' => 'articles']))
            ->assertSee('Tips baterai')->assertDontSee('Menambah produk');
    }
}
