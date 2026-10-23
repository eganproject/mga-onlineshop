<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BottomNavTest extends TestCase
{
    use RefreshDatabase;

    private const NAV = 'aria-label="Navigasi bawah"';

    public function test_storefront_pages_show_mobile_bottom_nav_with_explore_sheet(): void
    {
        Category::factory()->create(['name' => 'Powerbank']);

        $this->get(route('home'))->assertOk()
            ->assertSee(self::NAV, false)
            ->assertSee('viewport-fit=cover', false)
            ->assertSee('id="explore-sheet"', false)
            ->assertSee('Jelajahi')
            ->assertSee('Powerbank')
            ->assertSeeInOrder(['Beranda', 'Kategori', 'Keranjang', 'Wishlist', 'Masuk']);

        $this->get(route('products.index'))->assertOk()->assertSee(self::NAV, false);
        $this->get(route('cart.index'))->assertOk()->assertSee(self::NAV, false);
        $this->get(route('articles.index'))->assertOk()->assertSee(self::NAV, false);
    }

    public function test_bottom_nav_shows_cart_count_and_account_for_customer(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 3]);

        $this->get(route('account.dashboard'))->assertOk()
            ->assertSee(self::NAV, false)
            ->assertSee('Keranjang (3 barang)')
            ->assertSee('Akun');
    }

    public function test_admin_gets_promo_and_admin_tabs_instead_of_wishlist(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('home'))->assertOk()
            ->assertSee(self::NAV, false)
            ->assertSee(route('admin.dashboard'), false)
            ->assertDontSee(route('account.wishlist'), false);
    }

    public function test_focused_pages_hide_bottom_nav(): void
    {
        $inStock = Product::factory()->create(['stock' => 5]);
        $soldOut = Product::factory()->outOfStock()->create();

        // Halaman produk memakai bar "Tambah ke keranjang" sendiri.
        $this->get(route('products.show', $inStock))->assertOk()->assertDontSee(self::NAV, false);
        $this->get(route('products.show', $soldOut))->assertOk()->assertSee(self::NAV, false);

        $customer = User::factory()->create();
        Address::factory()->for($customer)->create();
        $this->actingAs($customer)->post(route('cart.store', $inStock));

        $this->get(route('checkout.index'))->assertOk()
            ->assertDontSee(self::NAV, false)
            ->assertSee('Total bayar');
    }

    public function test_admin_panel_keeps_default_viewport(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('viewport-fit=cover', false)
            ->assertDontSee(self::NAV, false);
    }
}
