<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_all_admin_pages_render(): void
    {
        $product = Product::factory()->create();
        $order = $this->makeOrder();

        $this->actingAs($this->admin);

        foreach ([
            route('admin.dashboard'),
            route('admin.products.index'),
            route('admin.products.create'),
            route('admin.products.edit', $product),
            route('admin.categories.index'),
            route('admin.categories.create'),
            route('admin.categories.edit', $product->category),
            route('admin.orders.index'),
            route('admin.orders.index', ['status' => 'paid', 'q' => 'MGA']),
            route('admin.orders.show', $order),
            route('admin.customers.index'),
            route('admin.customers.show', $order->user),
            route('admin.settings'),
            route('admin.profile.edit'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_product_with_formatted_price_and_image(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'name' => 'Kabel Data Super',
            'category_id' => $category->id,
            'price' => '150.000',
            'stock' => 10,
            'weight' => 100,
            'is_active' => 1,
            'images' => [UploadedFile::fake()->image('kabel.jpg'), UploadedFile::fake()->image('kabel-2.jpg')],
        ])->assertRedirect();

        $product = Product::firstWhere('name', 'Kabel Data Super');
        $this->assertSame(150000, $product->price);
        $this->assertSame(10, $product->stock);
        $this->assertSame('kabel-data-super', $product->slug);
        $this->assertCount(2, $product->images);
        Storage::disk('public')->assertExists($product->images->first()->path);
        $this->assertSame($product->images->first()->id, $product->coverImage->id);
    }

    public function test_admin_can_update_and_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'name' => 'Nama Baru',
            'price' => 5000,
            'stock' => 3,
            'weight' => 10,
        ])->assertRedirect();

        $this->assertFalse($product->refresh()->is_active);
        $this->assertSame('Nama Baru', $product->name);

        $this->delete(route('admin.products.destroy', $product))->assertRedirect();
        $this->assertModelMissing($product);
    }

    public function test_admin_can_manage_categories(): void
    {
        $this->actingAs($this->admin)->post(route('admin.categories.store'), [
            'name' => 'Casing HP',
            'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::firstWhere('slug', 'casing-hp');
        $this->assertNotNull($category);

        $this->delete(route('admin.categories.destroy', $category))->assertRedirect();
        $this->assertModelMissing($category);
    }

    public function test_shipping_an_order_requires_tracking_number(): void
    {
        $order = $this->makeOrder(OrderStatus::Processing);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'shipped'])
            ->assertSessionHasErrors('tracking_number');

        $this->patch(route('admin.orders.status', $order), ['status' => 'shipped', 'tracking_number' => 'JNE123'])
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
        $this->assertSame('JNE123', $order->tracking_number);
        $this->assertNotNull($order->shipped_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $order = $this->makeOrder(OrderStatus::PendingPayment);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'completed'])
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_cancelling_order_restores_stock(): void
    {
        $order = $this->makeOrder(OrderStatus::Paid);
        $item = $order->items->first();
        $stockBefore = $item->product->stock;

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame($stockBefore + $item->quantity, $item->product->refresh()->stock);
    }

    public function test_manual_paid_confirmation_settles_pending_payment(): void
    {
        $order = $this->makeOrder(OrderStatus::PendingPayment);
        $payment = $order->payments()->create(['gateway' => 'fake', 'status' => PaymentStatus::Pending, 'amount' => 30000]);

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), ['status' => 'paid']);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
    }

    public function test_admin_can_block_customer(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.customers.toggle', $customer))->assertRedirect();
        $this->assertFalse($customer->refresh()->is_active);
    }

    private function makeOrder(OrderStatus $status = OrderStatus::Paid): Order
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $product = Product::factory()->create(['price' => 10000]);

        $order = $customer->orders()->create([
            'code' => Order::generateCode(),
            'status' => $status,
            'subtotal' => 20000,
            'shipping_cost' => 10000,
            'total' => 30000,
            'recipient_name' => $address->recipient_name,
            'recipient_phone' => $address->phone,
            'shipping_address' => $address->fullAddress(),
            'courier' => 'jne',
            'courier_service' => 'REG',
            'paid_at' => $status === OrderStatus::PendingPayment ? null : now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 10000,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        return $order->load('items.product');
    }
}
