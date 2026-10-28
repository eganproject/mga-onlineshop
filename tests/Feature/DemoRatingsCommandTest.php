<?php

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Reviews\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoRatingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_raises_demo_reviews_until_average_reaches_minimum(): void
    {
        $product = Product::factory()->create();
        $this->review($product, 'demo1@mga.test', 3);
        $this->review($product, 'demo2@mga.test', 4);
        $this->review($product, 'demo3@mga.test', 4);
        $this->review($product, 'demo4@mga.test', 5);
        app(ReviewService::class)->syncSummary($product);
        $this->assertSame(4.0, $product->refresh()->rating_avg);

        $this->artisan('shop:demo-ratings', ['--force' => true])->assertSuccessful();

        // 3 dinaikkan dulu (4,5), lalu satu bintang 4 (4,75): cukup, sisanya tetap.
        $this->assertSame(4.8, $product->refresh()->rating_avg);
        $this->assertSame(4, $product->review_count);
        $this->assertSame([4, 5, 5, 5], ProductReview::orderBy('rating')->pluck('rating')->all());
        $this->assertSame(0, ProductReview::where('rating', 5)->where('body', 'Ulasan lama')->whereHas('user', fn ($q) => $q->where('email', 'demo1@mga.test'))->count(), 'Isi ulasan ikut diganti');
    }

    public function test_never_changes_reviews_from_real_customers(): void
    {
        $product = Product::factory()->create();
        $real = $this->review($product, 'budi@gmail.com', 1);
        $this->review($product, 'demo1@mga.test', 4);
        app(ReviewService::class)->syncSummary($product);

        $this->artisan('shop:demo-ratings', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, $real->refresh()->rating);
        $this->assertSame('Ulasan lama', $real->body);
        $this->assertSame(3.0, $product->refresh()->rating_avg);
    }

    public function test_dry_run_and_settings_are_untouched(): void
    {
        DB::table('settings')->insert(['key' => 'shop.integrations.midtrans.driver', 'value' => 'midtrans']);
        $product = Product::factory()->create();
        $this->review($product, 'demo1@mga.test', 3);
        $pending = $this->review($product, 'demo2@mga.test', 2, ReviewStatus::Pending);
        app(ReviewService::class)->syncSummary($product);

        $this->artisan('shop:demo-ratings', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(3, ProductReview::where('status', ReviewStatus::Approved)->value('rating'));

        $this->artisan('shop:demo-ratings', ['--force' => true])->assertSuccessful();
        $this->assertSame(5.0, $product->refresh()->rating_avg);
        $this->assertSame(2, $pending->refresh()->rating, 'Ulasan belum tayang tidak dihitung & tidak diubah');
        $this->assertDatabaseHas('settings', ['key' => 'shop.integrations.midtrans.driver', 'value' => 'midtrans']);
    }

    public function test_blocked_in_production(): void
    {
        $product = Product::factory()->create();
        $review = $this->review($product, 'demo1@mga.test', 2);
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('shop:demo-ratings', ['--force' => true])->assertFailed();
        $this->assertSame(2, $review->refresh()->rating);
    }

    private function review(Product $product, string $email, int $rating, ReviewStatus $status = ReviewStatus::Approved): ProductReview
    {
        $user = User::factory()->create(['email' => $email]);

        return ProductReview::factory()->for($product)->for($user)->create([
            'rating' => $rating,
            'body' => 'Ulasan lama',
            'status' => $status,
        ]);
    }
}
