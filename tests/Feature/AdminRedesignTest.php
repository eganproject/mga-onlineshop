<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Enums\VoucherType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tampilan admin baru: tema (terang/gelap), "Perlu tindakan" di dashboard, dan filter tambahan di daftar admin. */
class AdminRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->admin()->create();
    }

    public function test_admin_theme_comes_from_cookie_and_defaults_to_light(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('admin-ui', false)->assertSee('data-theme="light"', false)
            ->assertSee('Tema tampilan');

        $this->withUnencryptedCookie('admin_theme', 'dark')->get(route('admin.dashboard'))
            ->assertSee('data-theme="dark"', false);
        $this->withUnencryptedCookie('admin_theme', 'system')->get(route('admin.dashboard'))
            ->assertSee('data-theme="system"', false);
        // Nilai asing diabaikan.
        $this->withUnencryptedCookie('admin_theme', '"><script>')->get(route('admin.dashboard'))
            ->assertSee('data-theme="light"', false);
    }

    public function test_storefront_and_print_pages_are_not_themed(): void
    {
        $this->withUnencryptedCookie('admin_theme', 'dark')->get(route('home'))->assertOk()
            ->assertDontSee('admin-ui', false)
            ->assertDontSee('data-theme=', false);
    }

    public function test_dashboard_shows_actions_needed_per_permission(): void
    {
        Order::factory()->count(2)->create(['status' => OrderStatus::Paid]);
        Order::factory()->create()->forceFill(['warehouse_outbound_status' => 'failed'])->save();
        ProductReview::factory()->create();

        $this->actingAs($this->owner)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Perlu tindakan', false)
            ->assertSee('Pesanan perlu diproses')
            ->assertSee('Kirim ke gudang gagal')
            ->assertSee(route('admin.orders.index', ['gudang' => 'failed']), false)
            ->assertViewHas('actions', fn (array $actions) => collect($actions)->firstWhere('title', 'Pesanan perlu diproses')['count'] === 3
                && collect($actions)->firstWhere('title', 'Kirim ke gudang gagal')['count'] === 1);

        // Staf Gudang: tidak mengelola ulasan, jadi kotak ulasan tidak muncul.
        $warehouse = User::factory()->staff(StaffRole::Warehouse)->create();
        $this->actingAs($warehouse)->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('Ulasan menunggu');
    }

    public function test_orders_can_be_filtered_by_payment_method_and_warehouse_status(): void
    {
        $qris = Order::factory()->create();
        $qris->forceFill(['payment_method' => 'qris'])->save();
        $cash = Order::factory()->create();
        $cash->forceFill(['payment_method' => 'cash', 'warehouse_outbound_status' => 'approved'])->save();
        $failed = Order::factory()->create();
        $failed->forceFill(['warehouse_outbound_status' => 'failed'])->save();
        $unpaid = Order::factory()->unpaid()->create();

        $this->actingAs($this->owner);
        $codes = fn (array $query) => $this->get(route('admin.orders.index', $query))->assertOk()->viewData('orders')->pluck('code')->all();

        $this->assertSame([$qris->code], $codes(['bayar' => 'qris']));
        $this->assertSame([$cash->code], $codes(['bayar' => 'cash']));
        $this->assertSame([$failed->code], $codes(['gudang' => 'failed']));
        $this->assertEqualsCanonicalizing([$qris->code], $codes(['gudang' => 'belum']));
        $this->assertNotContains($unpaid->code, $codes(['gudang' => 'belum']));
        // Nilai asing diabaikan (semua pesanan tampil).
        $this->assertCount(4, $codes(['bayar' => 'bitcoin', 'gudang' => 'x']));
    }

    public function test_products_can_be_filtered_by_type(): void
    {
        $plain = Product::factory()->create(['name' => 'Polos']);
        $plain->forceFill(['warehouse_sku' => 'WH-1'])->save();
        $unpriced = Product::factory()->create(['name' => 'Tanpa harga', 'price' => 0, 'is_active' => false]);
        $wholesale = Product::factory()->create(['name' => 'Grosir', 'wholesale_tiers' => [['min_qty' => 10, 'price' => 1000]], 'price' => 5000]);

        $this->actingAs($this->owner);
        $names = fn (string $type) => $this->get(route('admin.products.index', ['jenis' => $type]))->assertOk()->viewData('products')->pluck('name')->all();

        $this->assertSame(['Tanpa harga'], $names('tanpa-harga'));
        $this->assertSame(['Grosir'], $names('grosir'));
        $this->assertNotContains('Polos', $names('gudang-belum'));
        $this->assertContains('Grosir', $names('gudang-belum'));
    }

    public function test_customers_staff_vouchers_categories_and_stock_have_extra_filters(): void
    {
        $buyer = User::factory()->create(['name' => 'Pembeli']);
        Order::factory()->create(['user_id' => $buyer->id]);
        User::factory()->create(['name' => 'Belum belanja']);
        $google = User::factory()->create(['name' => 'Akun Google']);
        $google->forceFill(['firebase_uid' => 'uid-1'])->save();

        $this->actingAs($this->owner);
        $customers = fn (array $q) => $this->get(route('admin.customers.index', $q))->assertOk()->viewData('customers')->pluck('name')->all();
        $this->assertSame(['Pembeli'], $customers(['belanja' => 'pernah']));
        $this->assertNotContains('Pembeli', $customers(['belanja' => 'belum']));
        $this->assertSame(['Akun Google'], $customers(['login' => 'google']));
        $this->assertCount(3, $customers(['from' => today()->format('Y-m-d'), 'to' => today()->format('Y-m-d')]));
        $this->assertCount(0, $customers(['to' => today()->subYear()->format('Y-m-d')]));

        User::factory()->staff(StaffRole::Warehouse)->inactive()->create(['name' => 'Staf lama']);
        $staff = $this->get(route('admin.staff.index', ['status' => 'nonaktif']))->assertOk()->viewData('staff')->pluck('name')->all();
        $this->assertSame(['Staf lama'], $staff);

        Voucher::factory()->create(['code' => 'ONGKIR', 'type' => VoucherType::FreeShipping, 'is_public' => true]);
        Voucher::factory()->create(['code' => 'POTONG']);
        $this->assertSame(['ONGKIR'], $this->get(route('admin.vouchers.index', ['jenis' => 'free_shipping']))->viewData('vouchers')->pluck('code')->all());
        $this->assertSame(['POTONG'], $this->get(route('admin.vouchers.index', ['tampil' => 'tersembunyi']))->viewData('vouchers')->pluck('code')->all());

        $parent = Category::factory()->create(['name' => 'Induk']);
        Category::factory()->create(['name' => 'Anak', 'parent_id' => $parent->id, 'is_active' => false]);
        $this->assertSame(['Anak'], $this->get(route('admin.categories.index', ['status' => 'nonaktif']))->viewData('categories')->pluck('name')->all());
        $this->assertSame(['Induk'], $this->get(route('admin.categories.index', ['tingkat' => 'induk']))->viewData('categories')->pluck('name')->all());

        $inCategory = Product::factory()->create(['name' => 'Di kategori', 'category_id' => $parent->id]);
        Product::factory()->create(['name' => 'Lain']);
        $this->assertSame([$inCategory->name], $this->get(route('admin.stock.index', ['kategori' => $parent->id]))->viewData('products')->pluck('name')->all());
    }
}
