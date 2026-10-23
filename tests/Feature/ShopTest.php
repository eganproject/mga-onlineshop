<?php

namespace Tests\Feature;

use App\Data\ShippingRate;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Orders\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_pages_render(): void
    {
        $product = Product::factory()->create(['is_featured' => true]);
        Product::factory()->inactive()->create(['name' => 'Produk Rahasia']);

        $this->get(route('home'))->assertOk()->assertSee($product->name);
        $this->get(route('products.index'))->assertOk()->assertSee($product->name)->assertDontSee('Produk Rahasia');
        $this->get(route('products.index', ['q' => $product->name, 'kategori' => $product->category->slug, 'urut' => 'termurah']))
            ->assertOk()->assertSee($product->name);
        $this->get(route('products.show', $product))->assertOk()->assertSee($product->formattedPrice());
    }

    public function test_catalog_can_filter_by_promo_and_price(): void
    {
        $cheap = Product::factory()->create(['name' => 'Kabel Murah', 'price' => 20000]);
        $sale = Product::factory()->create(['name' => 'Charger Diskon', 'price' => 80000, 'compare_at_price' => 100000]);
        $pricey = Product::factory()->create(['name' => 'Earphone Premium', 'price' => 400000]);

        $this->get(route('products.index', ['promo' => 1]))
            ->assertOk()->assertSee($sale->name)->assertDontSee($cheap->name)->assertDontSee($pricey->name);

        $this->get(route('products.index', ['harga_min' => 50000, 'harga_max' => 250000]))
            ->assertOk()->assertSee($sale->name)->assertDontSee($cheap->name)->assertDontSee($pricey->name);

        $this->get(route('products.index', ['harga_min' => 250000]))
            ->assertOk()->assertSee($pricey->name)->assertDontSee($sale->name);

        // Beranda menampilkan bagian promo bila ada produk diskon.
        $this->get(route('home'))->assertOk()->assertSee('Lagi diskon');
    }

    public function test_inactive_product_page_returns_404(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->get(route('products.show', $product))->assertNotFound();
    }

    public function test_guest_can_use_cart_and_quantity_is_capped_by_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->post(route('cart.store', $product), ['quantity' => 10])->assertRedirect();
        $this->get(route('cart.index'))->assertOk()->assertSee($product->name);
        $this->assertSame([$product->id => 3], session('cart'));

        $this->patch(route('cart.update', (string) $product->id), ['quantity' => 1]);
        $this->assertSame([$product->id => 1], session('cart'));

        $this->delete(route('cart.destroy', (string) $product->id));
        $this->assertSame([], session('cart'));
    }

    public function test_cart_tells_customer_when_stock_changed(): void
    {
        $kabel = Product::factory()->create(['name' => 'Kabel USB-C', 'stock' => 5]);
        $casing = Product::factory()->create(['name' => 'Casing Bening', 'stock' => 2]);

        $this->post(route('cart.store', $kabel), ['quantity' => 4]);
        $this->post(route('cart.store', $casing));

        $kabel->forceFill(['stock' => 2])->save();
        $casing->forceFill(['stock' => 0])->save();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Keranjangmu kami perbarui')
            ->assertSee('Jumlah Kabel USB-C disesuaikan menjadi 2 karena stok tersisa 2.')
            ->assertSee('Casing Bening dihapus dari keranjang')
            ->assertSee('Sisa 2');

        // Pesan hanya tampil sekali.
        $this->get(route('cart.index'))->assertOk()->assertDontSee('Keranjangmu kami perbarui');
        $this->assertSame([$kabel->id => 2], session('cart'));
    }

    public function test_out_of_stock_product_cannot_be_added(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->post(route('cart.store', $product))->assertSessionHas('error');
        $this->assertNull(session('cart'));
    }

    public function test_checkout_requires_login(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('login'));
    }

    public function test_customer_can_checkout_and_simulate_payment(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $product = Product::factory()->create(['price' => 50000, 'stock' => 5, 'weight' => 300]);

        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 2]);

        $this->get(route('checkout.index'))->assertOk()->assertSee('JNE');

        $response = $this->post(route('checkout.store'), [
            'address_id' => $address->id,
            'shipping' => 'jne|REG',
            'shipping_cost' => 1, // diabaikan, server menghitung ulang
        ]);

        $order = Order::first();
        $response->assertRedirect(route('account.orders.payment', $order));

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(100000, $order->subtotal);
        $this->assertSame(11000, $order->shipping_cost);
        $this->assertSame(111000, $order->total);
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertNull(session('cart'));
        $this->assertSame(PaymentStatus::Pending, $order->latestPayment->status);

        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('Bayar sekarang');
        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('Simulasikan bayar');

        $this->post(route('account.orders.simulate-payment', $order))->assertRedirect();
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->latestPayment->status);
    }

    public function test_checkout_fails_when_stock_runs_out_before_payment(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $product = Product::factory()->create(['stock' => 2]);

        $cart = app(CartService::class);
        $cart->add($product, quantity: 2);
        $cart->lines(); // keranjang sudah terbaca dengan stok lama

        // Pembeli lain membeli lebih dulu.
        $product->forceFill(['stock' => 1])->save();

        try {
            app(CheckoutService::class)->placeOrder($customer, $address, new ShippingRate('jne', 'JNE', 'REG', 'Reguler', 10000));
            $this->fail('Checkout seharusnya gagal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->refresh()->stock);
    }

    public function test_customer_can_cancel_unpaid_order_and_stock_returns(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 2]);
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'sicepat|REG']);

        $order = Order::first();
        $this->assertSame(3, $product->refresh()->stock);

        $this->post(route('account.orders.cancel', $order))->assertSessionHas('success');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_customer_cannot_see_other_customers_order(): void
    {
        $owner = User::factory()->create();
        $order = $owner->orders()->create([
            'code' => Order::generateCode(), 'subtotal' => 1, 'total' => 1,
            'recipient_name' => 'A', 'recipient_phone' => '0812', 'shipping_address' => 'X',
        ]);

        $this->actingAs(User::factory()->create())->get(route('account.orders.show', $order))->assertNotFound();
    }

    public function test_account_pages_render(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer);

        foreach ([
            route('account.dashboard'),
            route('account.orders.index'),
            route('account.addresses.index'),
            route('account.addresses.create'),
            route('account.addresses.edit', $address),
            route('account.profile.edit'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_first_address_becomes_default(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('account.addresses.store'), [
            'label' => 'Kantor',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province_id' => '1',
            'city_id' => '101',
            'district_id' => '10101',
            'address_line' => 'Jl. Sudirman 1',
        ])->assertRedirect(route('account.addresses.index'));

        $this->assertTrue($customer->addresses()->first()->is_default);
    }

    public function test_customer_can_update_profile_and_password(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->patch(route('account.profile.update'), [
            'name' => 'Nama Baru', 'email' => $customer->email, 'phone' => '0899',
        ])->assertSessionHas('success');
        $this->assertSame('Nama Baru', $customer->refresh()->name);

        $this->put(route('account.profile.password'), [
            'current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->put(route('account.profile.password'), [
            'current_password' => 'password', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHas('success');
    }
}
