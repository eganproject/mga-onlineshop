<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Catalog\SoldCountService;
use App\Services\Reviews\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProductStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_sold_count_follows_order_status(): void
    {
        $product = Product::factory()->create();
        $order = $this->order($product, 3, OrderStatus::PendingPayment);

        $this->assertSame(0, $product->refresh()->sold_count);

        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
        $this->assertSame(3, $product->refresh()->sold_count);

        $order->update(['status' => OrderStatus::Processing]);
        $this->assertSame(3, $product->refresh()->sold_count);

        $order->update(['status' => OrderStatus::Cancelled]);
        $this->assertSame(0, $product->refresh()->sold_count);
    }

    public function test_sync_recounts_all_products(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        Order::withoutEvents(function () use ($a, $b) {
            $this->order($a, 2, OrderStatus::Completed);
            $this->order($a, 5, OrderStatus::Delivered);
            $this->order($b, 4, OrderStatus::Cancelled);
        });

        app(SoldCountService::class)->sync();

        $this->assertSame(7, $a->refresh()->sold_count);
        $this->assertSame(0, $b->refresh()->sold_count);
    }

    public function test_sold_label_is_compact(): void
    {
        $product = new Product;
        foreach ([0 => '0', 87 => '87', 999 => '999', 1000 => '1rb', 1250 => '1,2rb', 9999 => '9,9rb', 10000 => '10rb+', 25300 => '20rb+'] as $sold => $label) {
            $product->sold_count = $sold;
            $this->assertSame($label, $product->soldLabel(), "sold {$sold}");
        }
    }

    public function test_cards_and_detail_show_rating_and_sold(): void
    {
        $product = Product::factory()->create(['name' => 'Tripod Banner Uji', 'is_featured' => true]);
        $product->forceFill(['rating_avg' => 4.7, 'review_count' => 12, 'sold_count' => 1250])->save();
        $plain = Product::factory()->create(['name' => 'Produk Baru Sepi']);

        $this->get(route('products.index'))->assertOk()
            ->assertSee('4,7')->assertSee('(12)')->assertSee('1,2rb terjual');

        $this->get(route('home'))->assertOk()->assertSee('1,2rb terjual');

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('12 ulasan')->assertSeeInOrder(['1,2rb', 'terjual']);

        // Belum ada ulasan & penjualan: baris statistik tidak tampil.
        $this->get(route('products.show', $plain))->assertOk()->assertDontSee('terjual');
    }

    public function test_listing_can_sort_by_best_selling_and_rating(): void
    {
        Product::factory()->create(['name' => 'Laris Sedikit'])->forceFill(['sold_count' => 3, 'rating_avg' => 5, 'review_count' => 1])->save();
        Product::factory()->create(['name' => 'Laris Banget'])->forceFill(['sold_count' => 40, 'rating_avg' => 4.2, 'review_count' => 9])->save();

        $this->get(route('products.index', ['urut' => 'terlaris']))->assertOk()->assertSeeInOrder(['Laris Banget', 'Laris Sedikit']);
        $this->get(route('products.index', ['urut' => 'rating']))->assertOk()->assertSeeInOrder(['Laris Sedikit', 'Laris Banget']);
    }

    public function test_demo_activity_command_rebuilds_orders_reviews_and_counts(): void
    {
        User::factory()->create(['email' => 'pelanggan@mga.test']);
        $products = Product::factory()->count(5)->create();
        $old = $this->order($products[0], 1, OrderStatus::Completed);

        $this->artisan('shop:demo-activity', ['--reset' => true, '--force' => true])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertGreaterThan(20, Order::count());
        $this->assertGreaterThan(0, ProductReview::count());
        $this->assertTrue(Product::where('sold_count', '>', 0)->exists());
        $this->assertTrue(Product::where('review_count', '>', 0)->exists());

        $product = Product::where('review_count', '>', 0)->first();
        $this->assertSame($product->reviews()->approved()->count(), $product->review_count);
    }

    public function test_reviews_can_be_filtered_by_star_and_product_can_be_shared(): void
    {
        $product = Product::factory()->create();
        foreach ([5 => 'Sangat puas sekali barangnya', 2 => 'Kurang sesuai harapan saya'] as $rating => $body) {
            ProductReview::factory()->approved()->for($product)->create(['rating' => $rating, 'body' => $body]);
        }
        app(ReviewService::class)->syncSummary($product);

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Sangat puas sekali')->assertSee('Kurang sesuai')
            ->assertSee('Bagikan ke WhatsApp');

        $this->get(route('products.show', [$product, 'bintang' => 2]))->assertOk()
            ->assertDontSee('Sangat puas sekali')->assertSee('Kurang sesuai')
            ->assertSee('Lihat semua ulasan');

        // Nilai di luar 1–5 diabaikan.
        $this->get(route('products.show', [$product, 'bintang' => 9]))->assertOk()->assertSee('Sangat puas sekali');
    }

    private function order(Product $product, int $qty, OrderStatus $status): Order
    {
        $order = Order::factory()->create(['status' => $status]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => $qty,
            'subtotal' => $product->price * $qty,
        ]);

        return $order;
    }
}
