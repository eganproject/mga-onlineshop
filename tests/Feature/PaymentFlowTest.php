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
        $this->assertSame([PaymentStatus::Cancelled], $order->payments()->pluck('status')->all());

        $this->post(route('account.orders.payment.method', $order), ['method' => 'qris']);

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

        $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'note' => 'Stok habis'])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->latestPayment->status);
    }

    public function test_payment_page_lists_methods_then_shows_instructions_inline(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 1]);
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG']);
        $order = $customer->orders()->firstOrFail();

        $this->assertSame(0, $order->payments()->count(), 'Tagihan dibuat setelah metode dipilih.');
        $this->get(route('account.orders.payment', $order))
            ->assertOk()
            ->assertSee('Pilih metode pembayaran')
            ->assertSee('BCA Virtual Account')
            ->assertSee('QRIS')
            ->assertSee('Tanpa biaya')
            ->assertDontSee('snap.js', false);

        $this->post(route('account.orders.payment.method', $order), ['method' => 'bni_va']);
        $payment = $order->latestPayment()->first();
        $number = $payment->instruction('number');
        $this->assertNotNull($number);

        $this->get(route('account.orders.payment', $order))
            ->assertOk()
            ->assertSee('Nomor Virtual Account BNI')
            ->assertSee(trim(chunk_split($number, 4, ' ')))
            ->assertSee('Cara bayar')
            ->assertSee('Mobile banking BNI')
            ->assertSee('Ganti metode')
            ->assertSee('Simulasikan bayar');

        $this->post(route('account.orders.payment.method', $order), ['method' => 'qris']);
        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('FAKE-QRIS-'.$order->code, false)->assertSee('Simpan QR');

        $this->post(route('account.orders.payment.method', $order), ['method' => 'mandiri_bill']);
        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('Kode perusahaan')->assertSee('70012');
    }

    public function test_status_endpoint_reports_payment_for_auto_refresh(): void
    {
        [$order] = $this->checkout();

        $this->getJson(route('account.orders.payment.status', $order))->assertOk()->assertJson(['status' => 'pending', 'redirect' => null]);

        $this->post(route('account.orders.simulate-payment', $order));

        $this->getJson(route('account.orders.payment.status', $order))
            ->assertOk()
            ->assertJson(['status' => 'paid', 'redirect' => route('account.orders.show', $order)]);

        $this->actingAs(User::factory()->create())->getJson(route('account.orders.payment.status', $order))->assertNotFound();
    }

    /** @return array{Order, User} */
    private function checkout(?Product $product = null, int $quantity = 1): array
    {
        $product ??= Product::factory()->create(['stock' => 5]);
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => $quantity]);
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'])->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $this->post(route('account.orders.payment.method', $order), ['method' => 'bca_va'])->assertRedirect(route('account.orders.payment', $order));

        return [$order, $customer];
    }
}
