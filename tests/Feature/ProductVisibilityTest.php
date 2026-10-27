<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_product_is_hidden_from_guests_and_customers(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->get(route('products.show', $product))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('products.show', $product))->assertNotFound();
    }

    public function test_staff_can_preview_inactive_product_from_admin(): void
    {
        $product = Product::factory()->inactive()->create();
        $this->actingAs(User::factory()->admin()->create());

        // Tombol "Lihat di toko" di admin harus bisa dibuka, bukan 404.
        $this->get(route('admin.products.index'))->assertOk()->assertSee(route('products.show', $product));

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Pratinjau admin')
            ->assertSee(route('admin.products.edit', $product))
            ->assertSee('noindex', false);
    }

    public function test_preview_hides_edit_link_for_staff_without_catalog_permission(): void
    {
        $product = Product::factory()->inactive()->create();
        $this->actingAs(User::factory()->staff(StaffRole::Warehouse)->create());

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Pratinjau admin')
            ->assertDontSee(route('admin.products.edit', $product));
    }

    public function test_active_product_has_no_preview_banner(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('Pratinjau admin');
    }

    public function test_customer_pages_do_not_link_to_deactivated_products(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Rak Brosur Lama']);
        $order = Order::factory()->for($customer)->create();
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
            'price' => 50000, 'quantity' => 1, 'weight' => 500, 'subtotal' => 50000,
        ]);
        $customer->wishlist()->attach($product);
        $product->forceFill(['is_active' => false])->save();

        $this->actingAs($customer);
        $url = route('products.show', $product);

        $this->get(route('account.wishlist'))->assertOk()
            ->assertSee('Rak Brosur Lama')
            ->assertSee('Tidak dijual lagi')
            ->assertDontSee($url);

        $this->get(route('account.orders.show', $order))->assertOk()
            ->assertSee('Rak Brosur Lama')
            ->assertDontSee($url);
    }
}
