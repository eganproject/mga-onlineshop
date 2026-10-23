<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Alur pembayaran dengan driver "fake" (tanpa API key). */
class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_page_is_only_for_owner_and_pending_orders(): void
    {
        [$order, $customer] = $this->checkout();

        $this->actingAs(User::factory()->create())->get(route('account.orders.payment', $order))->assertNotFound();

        $this->actingAs($customer)->post(route('account.orders.simulate-payment', $order));
        $this->get(route('account.orders.payment', $order))->assertRedirect(route('account.orders.show', $order));
    }

    public function test_overdue_orders_are_cancelled_and_stock_released(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        [$order] = $this->checkout($product, quantity: 2);
        $this->assertSame(3, $product->refresh()->stock);

        $this->travel(23)->hours();
        $this->artisan('shop:expire-payments')->expectsOutputToContain('0 pesanan')->assertSuccessful();

        $this->travel(2)->hours();
        $this->artisan('shop:expire-payments')->expectsOutputToContain('1 pesanan')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Expired, $order->latestPayment->status);
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'type' => 'cancellation', 'quantity' => 2]);

        // Dijalankan lagi tidak mengembalikan stok dua kali.
        $this->artisan('shop:expire-payments')->assertSuccessful();
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_cannot_create_new_payment_link_after_deadline(): void
    {
        [$order] = $this->checkout();
        $this->travel(25)->hours();

        $this->get(route('account.orders.payment', $order))
            ->assertOk()
            ->assertSee('Batas waktu pembayaran pesanan ini sudah lewat.');

        $this->post(route('account.orders.payment.regenerate', $order))->assertSessionHas('error');
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_regenerate_replaces_pending_fake_payment(): void
    {
        [$order] = $this->checkout();

        $this->post(route('account.orders.payment.regenerate', $order))->assertSessionHas('success');

        $this->assertSame(
            [PaymentStatus::Cancelled, PaymentStatus::Pending],
            $order->payments()->orderBy('id')->pluck('status')->all(),
        );
        $this->assertSame('FAKE-'.$order->code.'-2', $order->latestPayment()->first()->external_id);
    }

    public function test_admin_cancel_voids_pending_payment(): void
    {
        [$order] = $this->checkout();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->latestPayment->status);
    }

    /** @return array{Order, User} */
    private function checkout(?Product $product = null, int $quantity = 1): array
    {
        $product ??= Product::factory()->create(['stock' => 5]);
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => $quantity]);
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'])->assertRedirect();

        return [$customer->orders()->latest('id')->firstOrFail(), $customer];
    }
}
