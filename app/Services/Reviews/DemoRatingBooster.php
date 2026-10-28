<?php

namespace App\Services\Reviews;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menaikkan rata-rata rating produk di data demo: ulasan dari akun contoh
 * (email @mga.test) yang bintangnya paling rendah dinaikkan jadi bintang 5
 * (judul & isi ikut diganti supaya sesuai) sampai rata-rata produk mencapai
 * target. Ulasan akun lain hanya ikut diubah bila diminta (`$anyAuthor`, mis.
 * akun uji coba di situs demo) atau bila ID-nya diberikan (ulasan yang baru
 * dibuat seeder). Hanya menyentuh tabel
 * `product_reviews` dan kolom ringkasan `rating_avg`/`review_count` produk.
 */
class DemoRatingBooster
{
    /** Domain email akun contoh (demo1..12@mga.test, pelanggan@mga.test). */
    public const DEMO_EMAIL_DOMAIN = '@mga.test';

    /** @var list<array{0: ?string, 1: string}> */
    public const FIVE_STAR_SAMPLES = [
        ['Mantap, sesuai deskripsi', 'Barang sesuai foto, packing rapi pakai bubble wrap dan kardus tebal. Besoknya sudah sampai.'],
        ['Kokoh dan rapi', 'Bahannya tebal, finishing rapi, tidak ada goresan. Langsung dipakai untuk display di toko.'],
        ['Recommended seller', 'Sudah order kedua kalinya, selalu puas. Admin ramah dan membantu pilih ukuran yang cocok.'],
        [null, 'Pengiriman cepat, barang aman tidak pecah. Terima kasih MGA!'],
        ['Bagus untuk event', 'Dipakai untuk pameran, gampang dirakit dan dibongkar lagi. Pengunjung jadi lebih mudah lihat promo kami.'],
        ['Akrilik bening', 'Akriliknya bening, tidak buram. Brosur kelihatan jelas dan rapi di meja resepsionis.'],
        ['Repeat order', 'Beli lagi untuk cabang baru, kualitas tetap konsisten. Harga grosirnya juga bersahabat.'],
        ['Puas belanja di sini', 'Kualitas melebihi harga, pengiriman cepat dan seller responsif. Pasti order lagi.'],
    ];

    public function __construct(private ReviewService $reviews) {}

    /**
     * @param  float  $target  Rata-rata minimal (mentah, sebelum dibulatkan 1 desimal).
     * @param  list<int>|null  $productIds  Batasi ke produk tertentu; null = semua produk berulasan.
     * @param  bool  $anyAuthor  Ikutkan ulasan dari akun mana pun, bukan hanya akun contoh @mga.test.
     * @param  list<int>|null  $reviewIds  Hanya ulasan ini yang boleh diubah (dipakai seeder).
     * @return list<array{product: Product, before: float, after: float, changed: int, reached: bool}>
     */
    public function boost(float $target, ?array $productIds = null, bool $dryRun = false, bool $anyAuthor = false, ?array $reviewIds = null): array
    {
        $products = Product::query()
            ->whereHas('reviews', fn (Builder $q) => $q->approved())
            ->when($productIds !== null, fn (Builder $q) => $q->whereKey($productIds))
            ->orderBy('id')
            ->get();

        $results = [];
        foreach ($products as $product) {
            $result = DB::transaction(fn () => $this->boostProduct($product, $target, $dryRun, $anyAuthor, $reviewIds));
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /** @return array{product: Product, before: float, after: float, changed: int, reached: bool}|null */
    private function boostProduct(Product $product, float $target, bool $dryRun, bool $anyAuthor, ?array $reviewIds): ?array
    {
        $approved = $product->reviews()->approved()->lockForUpdate()->get(['id', 'rating', 'user_id']);
        $count = $approved->count();
        $sum = (int) $approved->sum('rating');
        $before = $sum / $count;

        if ($before >= $target) {
            return null;
        }

        // Bintang terendah dulu.
        $candidates = $product->reviews()->approved()
            ->where('rating', '<', 5)
            ->when($reviewIds !== null, fn (Builder $q) => $q->whereKey($reviewIds))
            ->when($reviewIds === null && ! $anyAuthor, fn (Builder $q) => $q
                ->whereHas('user', fn (Builder $u) => $u->where('email', 'like', '%'.self::DEMO_EMAIL_DOMAIN)))
            ->orderBy('rating')
            ->orderBy('id')
            ->get();

        $changed = 0;
        foreach ($candidates as $review) {
            if ($sum / $count >= $target) {
                break;
            }

            $sum += 5 - $review->rating;
            $changed++;

            if (! $dryRun) {
                [$title, $body] = self::FIVE_STAR_SAMPLES[$review->id % count(self::FIVE_STAR_SAMPLES)];
                // Tanpa menyentuh updated_at supaya tanggal ulasan contoh tetap natural.
                $review->timestamps = false;
                $review->forceFill(['rating' => 5, 'title' => $title, 'body' => $body])->save();
            }
        }

        if (! $dryRun && $changed > 0) {
            $this->reviews->syncSummary($product);
        }

        return [
            'product' => $product,
            'before' => round($before, 2),
            'after' => round($sum / $count, 2),
            'changed' => $changed,
            'reached' => $sum / $count >= $target,
        ];
    }
}
