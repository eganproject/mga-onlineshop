<?php

namespace App\Services\Storefront;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Isi tab "Best Seller" di beranda.
 *
 * Mode otomatis: terlaris 90 hari dari pesanan berstatus revenue, dilengkapi
 * produk unggulan lalu terbaru. Mode manual: produk pilihan admin sesuai urutan
 * di Admin › Tampilan Beranda (yang nonaktif/stok habis dilewati), lalu bila
 * diaktifkan sisanya dilengkapi otomatis.
 */
class BestSellers
{
    public const LIMIT = 12;

    /** Batas jumlah pilihan manual (lebih dari LIMIT sebagai cadangan bila ada yang habis). */
    public const MAX_PICKS = 24;

    public const CACHE_KEY = 'home:best-sellers';

    public static function isManual(): bool
    {
        return config('shop.storefront.best_seller_mode') === 'manual';
    }

    /** @return list<int> id produk pilihan admin, sesuai urutan */
    public static function pickedIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) config('shop.storefront.best_seller_products', []))));
    }

    /** @return Collection<int, Product> dengan atribut sold_recent (90 hari), rank_best, rank_new */
    public function forHome(): Collection
    {
        $sold = $this->soldCounts();
        $products = collect();

        if (self::isManual()) {
            $products = $this->picked();

            if (! config('shop.storefront.best_seller_fill', true)) {
                return $this->rank($products, $sold);
            }
        }

        $products = $products->concat(
            Product::active()->with('category')
                ->whereIn('id', array_keys($sold))
                ->whereNotIn('id', $products->pluck('id'))
                ->get()
                ->sortByDesc(fn (Product $p) => $sold[$p->id] ?? 0)
        )->take(self::LIMIT);

        foreach ([
            fn ($q) => $q->where('is_featured', true),
            fn ($q) => $q,
        ] as $filler) {
            if ($products->count() >= self::LIMIT) {
                break;
            }

            $products = $products->concat(
                Product::active()->with('category')->tap($filler)
                    ->whereNotIn('id', $products->pluck('id'))
                    ->latest()->take(self::LIMIT - $products->count())->get()
            );
        }

        return $this->rank($products, $sold);
    }

    /** Pilihan manual yang masih aktif & ada stoknya, sesuai urutan admin. */
    private function picked(): Collection
    {
        $ids = self::pickedIds();

        if ($ids === []) {
            return collect();
        }

        $order = array_flip($ids);

        return Product::active()->with('category')->whereIn('id', $ids)->where('stock', '>', 0)->get()
            ->sortBy(fn (Product $p) => $order[$p->id])
            ->take(self::LIMIT)
            ->values();
    }

    /** @return array<int, int> product_id => jumlah terjual 90 hari terakhir */
    private function soldCounts(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(30), fn () => OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q
                ->whereIn('status', OrderStatus::revenueStatuses())
                ->where('created_at', '>=', now()->subDays(90)))
            ->selectRaw('product_id, SUM(quantity) as sold')
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->limit(self::LIMIT * 2)
            ->pluck('sold', 'product_id')
            ->map(fn ($qty) => (int) $qty)
            ->all());
    }

    /** Urutan "terlaris" & "terbaru" dipakai untuk pengurutan di sisi browser. */
    private function rank(Collection $products, array $sold): Collection
    {
        $products = $products->values();
        $newest = $products->sortByDesc(fn (Product $p) => [$p->created_at?->timestamp ?? 0, $p->id])->pluck('id')->flip();

        return $products->each(function (Product $p, int $rank) use ($sold, $newest) {
            $p->setAttribute('sold_recent', $sold[$p->id] ?? 0);
            $p->setAttribute('rank_best', $rank);
            $p->setAttribute('rank_new', $newest[$p->id]);
        });
    }
}
