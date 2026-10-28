<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\Reviews\DemoRatingBooster;
use App\Services\Reviews\ReviewService;
use Illuminate\Database\Seeder;

/**
 * Ulasan contoh dari pembeli pesanan contoh yang sudah selesai, supaya bintang
 * & jumlah ulasan terlihat di kartu dan detail produk saat demo. Sebagian
 * pembeli saja yang mengulas (seperti toko sungguhan). Rata-rata tiap produk
 * dijaga minimal MIN_AVERAGE (lewat DemoRatingBooster). Tidak jalan di produksi.
 */
class DemoReviewSeeder extends Seeder
{
    /** Rata-rata rating minimal tiap produk di data demo. */
    public const MIN_AVERAGE = 4.7;

    /** @var list<array{0: int, 1: ?string, 2: string}> */
    private const SAMPLES = [
        [5, 'Mantap, sesuai deskripsi', 'Barang sesuai foto, packing rapi pakai bubble wrap dan kardus tebal. Besoknya sudah sampai.'],
        [5, 'Kokoh dan rapi', 'Bahannya tebal, finishing rapi, tidak ada goresan. Langsung dipakai untuk display di toko.'],
        [5, 'Recommended seller', 'Sudah order kedua kalinya, selalu puas. Admin ramah dan membantu pilih ukuran yang cocok.'],
        [5, null, 'Pengiriman cepat, barang aman tidak pecah. Terima kasih MGA!'],
        [5, 'Bagus untuk event', 'Dipakai untuk pameran, gampang dirakit dan dibongkar lagi. Pengunjung jadi lebih mudah lihat promo kami.'],
        [4, 'Bagus', 'Kualitas oke untuk harganya. Pengiriman agak lama karena kurir, tapi seller responsif.'],
        [4, null, 'Barang bagus, cuma plastik pelindungnya agak susah dilepas. Overall puas.'],
        [4, 'Sesuai harga', 'Ukuran pas dengan deskripsi. Semoga awet dipakai harian di kasir.'],
        [5, 'Akrilik bening', 'Akriliknya bening, tidak buram. Brosur kelihatan jelas dan rapi di meja resepsionis.'],
        [5, 'Repeat order', 'Beli lagi untuk cabang baru, kualitas tetap konsisten. Harga grosirnya juga bersahabat.'],
        [4, 'Cepat sampai', 'Dikirim di hari yang sama, sampai dalam 2 hari. Barang sesuai pesanan.'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $items = OrderItem::query()
            ->with('order:id,user_id,completed_at')
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Completed))
            ->get();

        $touched = [];
        $created = [];
        foreach ($items as $i => $item) {
            // Kira-kira 45% pembeli menulis ulasan, satu ulasan per pelanggan per produk.
            if (random_int(1, 100) > 45) {
                continue;
            }

            $review = ProductReview::firstOrNew(['user_id' => $item->order->user_id, 'product_id' => $item->product_id]);
            if ($review->exists) {
                continue;
            }

            [$rating, $title, $body] = self::SAMPLES[array_rand(self::SAMPLES)];
            $at = ($item->order->completed_at ?? now())->copy()->addDays(random_int(0, 3))->min(now());

            $review->forceFill([
                'user_id' => $item->order->user_id,
                'product_id' => $item->product_id,
                'order_id' => $item->order_id,
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
                'status' => ReviewStatus::Approved,
                'approved_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();

            $touched[$item->product_id] = true;
            $created[] = $review->id;
        }

        $reviews = app(ReviewService::class);
        Product::whereKey(array_keys($touched))->get()->each(fn (Product $product) => $reviews->syncSummary($product));

        // Produk dengan sedikit ulasan bisa kebetulan dapat banyak bintang 4. Semua
        // ulasan buatan seeder ini boleh dinaikkan, siapa pun akun pembelinya.
        app(DemoRatingBooster::class)->boost(self::MIN_AVERAGE, array_keys($touched), reviewIds: $created);
    }
}
