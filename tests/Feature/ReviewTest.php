<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_only_customers_with_completed_order_can_review(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $payload = ['rating' => 5, 'title' => 'Mantap', 'body' => 'Barangnya bagus sekali, sesuai foto.'];

        // Belum pernah beli.
        $this->actingAs($customer)->post(route('reviews.store', $product), $payload)->assertSessionHas('error');

        // Sudah beli tapi pesanan belum selesai.
        $this->orderFor($customer, $product, OrderStatus::Shipped);
        $this->post(route('reviews.store', $product), $payload)->assertSessionHas('error');
        $this->assertDatabaseCount('product_reviews', 0);

        $order = $this->orderFor($customer, $product, OrderStatus::Completed);
        $this->get(route('products.show', $product))->assertSee('Tulis ulasan');
        $this->post(route('reviews.store', $product), $payload)->assertRedirect()->assertSessionHas('success');

        $review = ProductReview::sole();
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertSame($order->id, $review->order_id);
        // Belum tayang, jadi ringkasan rating belum berubah.
        $this->assertSame(0, $product->refresh()->review_count);
    }

    public function test_review_validation(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $this->orderFor($customer, $product, OrderStatus::Completed);

        $this->actingAs($customer)->post(route('reviews.store', $product), ['rating' => 6, 'body' => 'pendek'])
            ->assertSessionHasErrorsIn('review', ['rating', 'body']);
    }

    public function test_editing_review_updates_instead_of_duplicating(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $this->orderFor($customer, $product, OrderStatus::Completed);

        $this->actingAs($customer)->post(route('reviews.store', $product), ['rating' => 3, 'body' => 'Lumayan, tapi agak lama sampai.']);
        ProductReview::sole()->forceFill(['status' => ReviewStatus::Approved])->save();

        $this->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Setelah dipakai seminggu ternyata awet.']);

        $review = ProductReview::sole();
        $this->assertSame(5, $review->rating);
        $this->assertSame(ReviewStatus::Pending, $review->status, 'Ulasan yang diedit dimoderasi ulang');
    }

    public function test_admin_moderation_updates_product_rating(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $first = ProductReview::factory()->for($product)->create(['rating' => 5, 'body' => 'Ulasan bintang lima']);
        $second = ProductReview::factory()->for($product)->create(['rating' => 2, 'body' => 'Ulasan bintang dua']);

        $this->actingAs($admin)->get(route('admin.reviews.index'))->assertOk()->assertSee('Ulasan bintang lima');
        $this->get(route('admin.reviews.index', ['status' => 'pending', 'rating' => 2]))
            ->assertOk()->assertSee('Ulasan bintang dua')->assertDontSee('Ulasan bintang lima');

        $this->patch(route('admin.reviews.update', $first), ['status' => 'approved'])->assertRedirect();
        $this->patch(route('admin.reviews.update', $second), ['status' => 'approved']);
        $product->refresh();
        $this->assertSame(2, $product->review_count);
        $this->assertSame(3.5, $product->rating_avg);

        $this->patch(route('admin.reviews.update', $second), ['status' => 'rejected']);
        $this->patch(route('admin.reviews.update', $first), ['admin_reply' => 'Terima kasih, Kak!']);
        $product->refresh();
        $this->assertSame(1, $product->review_count);
        $this->assertSame(5.0, $product->rating_avg);

        // Hanya ulasan tayang yang muncul di halaman produk, lengkap dengan balasan toko.
        auth()->logout();
        $this->get(route('products.show', $product))
            ->assertSee('Ulasan bintang lima')->assertSee('Terima kasih, Kak!')
            ->assertDontSee('Ulasan bintang dua')
            ->assertSee('"aggregateRating"', false);

        $this->actingAs($admin)->delete(route('admin.reviews.destroy', $first))->assertRedirect();
        $this->assertSame(0, $product->refresh()->review_count);
    }

    public function test_customer_cannot_moderate(): void
    {
        $review = ProductReview::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.reviews.update', $review), ['status' => 'approved'])
            ->assertForbidden();
    }

    public function test_account_reviews_page_lists_products_to_review(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Kabel Data Awet']);
        $this->orderFor($customer, $product, OrderStatus::Completed);

        $this->actingAs($customer)->get(route('account.reviews.index'))
            ->assertOk()->assertSee('Menunggu ulasanmu')->assertSee('Kabel Data Awet');
    }

    private function orderFor(User $customer, Product $product, OrderStatus $status): Order
    {
        $order = $customer->orders()->create([
            'code' => Order::generateCode(),
            'status' => $status,
            'subtotal' => $product->price,
            'total' => $product->price,
            'recipient_name' => $customer->name,
            'recipient_phone' => '08123',
            'shipping_address' => 'Jl. Contoh',
            'completed_at' => $status === OrderStatus::Completed ? now() : null,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'subtotal' => $product->price,
        ]);

        return $order;
    }
}
