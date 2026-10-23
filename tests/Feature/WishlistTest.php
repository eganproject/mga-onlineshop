<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $product = Product::factory()->create();

        $this->post(route('wishlist.toggle', $product))->assertRedirect(route('login'));
        $this->get(route('products.show', $product))->assertOk()->assertSee('Masuk untuk menyimpan ke wishlist');
    }

    public function test_customer_can_toggle_wishlist(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Powerbank Favorit']);

        $this->actingAs($customer)->post(route('wishlist.toggle', $product))->assertSessionHas('success', 'Disimpan ke wishlist.');
        $this->assertTrue($customer->wishlist()->whereKey($product->id)->exists());

        $this->get(route('account.wishlist'))->assertOk()->assertSee('Powerbank Favorit');
        $this->get(route('products.show', $product))->assertSee('Hapus dari wishlist');

        $this->postJson(route('wishlist.toggle', $product))
            ->assertOk()
            ->assertJson(['wishlisted' => false, 'count' => 0]);
        $this->assertFalse($customer->wishlist()->exists());
    }

    public function test_inactive_product_cannot_be_added_but_can_be_removed(): void
    {
        $customer = User::factory()->create();
        $inactive = Product::factory()->inactive()->create();

        $this->actingAs($customer)->post(route('wishlist.toggle', $inactive))->assertNotFound();

        $customer->wishlist()->attach($inactive);
        $this->get(route('account.wishlist'))->assertOk()->assertSee('Produk tidak lagi dijual');
        $this->post(route('wishlist.toggle', $inactive))->assertRedirect();
        $this->assertFalse($customer->wishlist()->exists());
    }

    public function test_admin_has_no_wishlist(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post(route('wishlist.toggle', $product))->assertForbidden();
    }
}
