<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_mentions_quantity_just_added_not_cart_total(): void
    {
        $product = Product::factory()->create(['name' => 'Kabel Data', 'stock' => 10]);

        $this->post(route('cart.store', $product), ['quantity' => 2])
            ->assertSessionHas('success', '2 pcs Kabel Data ditambahkan ke keranjang.');

        // Tambah 1 lagi: pesan menyebut 1 pcs, total (3) hanya sebagai keterangan.
        $this->post(route('cart.store', $product), ['quantity' => 1])
            ->assertSessionHas('success', '1 pcs Kabel Data ditambahkan ke keranjang. Total di keranjang sekarang 3 pcs.');
    }

    public function test_add_to_cart_via_fetch_returns_json_without_redirect(): void
    {
        $product = Product::factory()->create(['name' => 'Charger', 'stock' => 4]);

        $this->postJson(route('cart.store', $product), ['quantity' => 1])
            ->assertOk()
            ->assertJson([
                'type' => 'success',
                'message' => '1 pcs Charger ditambahkan ke keranjang.',
                'added' => 1,
                'quantity' => 1,
                'cart_count' => 1,
            ]);

        // Melebihi stok: hanya sisa stok yang ditambahkan, dengan peringatan.
        $this->postJson(route('cart.store', $product), ['quantity' => 9])
            ->assertOk()
            ->assertJson(['type' => 'warning', 'added' => 3, 'quantity' => 4, 'cart_count' => 4])
            ->assertJsonPath('message', 'Hanya 3 pcs Charger yang bisa ditambahkan karena stok terbatas. Total di keranjang sekarang 4 pcs.');

        $this->postJson(route('cart.store', $product))
            ->assertOk()
            ->assertJson(['type' => 'warning', 'added' => 0, 'cart_count' => 4]);

        $this->assertSame([$product->id => 4], session('cart'));
    }

    public function test_fetch_errors_return_422_with_message(): void
    {
        $product = Product::factory()->create(['variant_label' => 'Warna']);
        ProductVariant::factory()->for($product)->create(['stock' => 3]);

        $this->postJson(route('cart.store', $product))
            ->assertStatus(422)
            ->assertJson(['type' => 'error', 'message' => 'Pilih warna terlebih dahulu.']);

        $habis = Product::factory()->create(['stock' => 0]);
        $this->postJson(route('cart.store', $habis))->assertStatus(422)->assertJson(['type' => 'error']);

        $this->assertNull(session('cart'));
    }

    public function test_buy_now_via_fetch_returns_checkout_redirect(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->postJson(route('cart.store', $product), ['buy_now' => 1])
            ->assertOk()
            ->assertJson(['redirect' => route('checkout.index')]);
    }

    public function test_cart_page_updates_return_fresh_cart_html(): void
    {
        $product = Product::factory()->create(['name' => 'Casing HP', 'stock' => 3, 'price' => 25000]);
        $this->post(route('cart.store', $product));
        $key = (string) $product->id;

        $this->patchJson(route('cart.update', $key), ['quantity' => 2])
            ->assertOk()
            ->assertJson(['cart_count' => 2, 'message' => null])
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'Rp50.000') && str_contains($html, '2 item'));

        $this->patchJson(route('cart.update', $key), ['quantity' => 5])
            ->assertOk()
            ->assertJson(['type' => 'error', 'cart_count' => 3]);

        $this->deleteJson(route('cart.destroy', $key))
            ->assertOk()
            ->assertJson(['type' => 'success', 'cart_count' => 0])
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'Keranjangmu masih kosong'));
    }

    public function test_pages_mark_cart_forms_and_badges_for_live_update(): void
    {
        $simple = Product::factory()->create(['name' => 'Produk Biasa', 'is_featured' => true]);
        $withVariants = Product::factory()->create(['name' => 'Produk Varian', 'is_featured' => true, 'variant_label' => 'Warna']);
        ProductVariant::factory()->for($withVariants)->create(['stock' => 3]);
        $withVariants->refresh()->syncVariantSummary();

        $this->post(route('cart.store', $simple), ['quantity' => 2]);

        $page = $this->get(route('products.index'))->assertOk()
            ->assertSee('data-cart-count="2"', false)
            ->assertSee('$store.cart.count', false)
            ->assertSee('data-cart-form', false);

        // Kartu produk bervarian mengarah ke halaman produk, bukan langsung ke keranjang.
        $page->assertSee('Pilih warna')->assertDontSee('action="'.route('cart.store', $withVariants).'"', false);

        $this->get(route('products.show', $simple))->assertOk()
            ->assertSee('id="add-to-cart"', false)
            ->assertSee('data-cart-native', false);

        $this->get(route('cart.index'))->assertOk()->assertSee('data-cart-root', false);
    }
}
