<?php

namespace App\Services\Reviews;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Semua perubahan ulasan lewat sini supaya ringkasan rating produk selalu sinkron. */
class ReviewService
{
    /** Pesanan selesai terbaru milik pelanggan yang berisi produk ini (syarat menulis ulasan). */
    public function purchaseFor(User $user, Product $product): ?Order
    {
        return $user->orders()
            ->where('status', OrderStatus::Completed)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->latest('completed_at')
            ->first();
    }

    public function canReview(User $user, Product $product): bool
    {
        return $user->isCustomer() && $this->purchaseFor($user, $product) !== null;
    }

    /**
     * Simpan ulasan baru atau perbarui ulasan lama. Ulasan yang diedit masuk
     * antrean moderasi lagi (kecuali auto-approve aktif).
     *
     * @param  array{rating: int, title?: ?string, body: string}  $data
     */
    public function submit(User $user, Product $product, array $data): ProductReview
    {
        $order = $this->purchaseFor($user, $product);

        abort_unless($order, 403, 'Ulasan hanya bisa ditulis setelah pesanan selesai.');

        return DB::transaction(function () use ($user, $product, $order, $data) {
            $review = ProductReview::firstOrNew(['user_id' => $user->id, 'product_id' => $product->id]);
            $review->fill($data);
            $review->user_id = $user->id;
            $review->product_id = $product->id;
            $review->order_id = $order->id;

            $autoApprove = (bool) config('shop.reviews.auto_approve');
            $review->status = $autoApprove ? ReviewStatus::Approved : ReviewStatus::Pending;
            $review->approved_at = $autoApprove ? now() : null;
            $review->save();

            $this->syncSummary($product);

            return $review;
        });
    }

    public function moderate(ProductReview $review, ReviewStatus $status): void
    {
        DB::transaction(function () use ($review, $status) {
            $review->status = $status;
            $review->approved_at = $status === ReviewStatus::Approved ? ($review->approved_at ?? now()) : null;
            $review->save();

            $this->syncSummary($review->product);
        });
    }

    public function reply(ProductReview $review, ?string $reply): void
    {
        $review->admin_reply = filled($reply) ? trim($reply) : null;
        $review->save();
    }

    public function delete(ProductReview $review): void
    {
        DB::transaction(function () use ($review) {
            $product = $review->product;
            $review->delete();
            $this->syncSummary($product);
        });
    }

    /** Hitung ulang rata-rata rating & jumlah ulasan yang tayang. */
    public function syncSummary(Product $product): void
    {
        $stats = $product->reviews()->approved()
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->toBase()
            ->first();

        $product->forceFill([
            'review_count' => (int) $stats->total,
            'rating_avg' => round((float) $stats->average, 1),
        ])->saveQuietly();
    }

    /**
     * Jumlah ulasan tayang per bintang (5..1) untuk grafik ringkasan.
     *
     * @return array<int, int>
     */
    public function distribution(Product $product): array
    {
        $counts = $product->reviews()->approved()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return collect(range(5, 1))->mapWithKeys(fn ($star) => [$star => (int) ($counts[$star] ?? 0)])->all();
    }
}
