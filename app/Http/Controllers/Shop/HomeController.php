<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const BEST_SELLER_LIMIT = 12;

    public function __invoke(): View
    {
        return view('shop.home', [
            'banners' => Banner::visible()->take(8)->get(),
            'categories' => Category::active()->root()
                ->withCount(['products' => fn ($q) => $q->active()])
                ->with(['children' => fn ($q) => $q->active()->withCount(['products' => fn ($q) => $q->active()])])
                ->orderBy('sort_order')->take(12)->get()
                // Jumlah produk induk termasuk produk di subkategorinya.
                ->each(fn (Category $c) => $c->products_count += $c->children->sum('products_count')),
            'bestSellers' => $this->bestSellers(),
            'onSale' => Product::active()->with('category')->onSale()->latest()->take(4)->get(),
            'latestArticles' => Article::published()->with('category:id,name,slug')->latest('published_at')->take(3)->get(),
        ]);
    }

    /**
     * Produk terlaris (jumlah terjual dari pesanan yang sudah dibayar, 90 hari
     * terakhir). Bila datanya belum cukup, dilengkapi produk unggulan lalu terbaru.
     */
    private function bestSellers(): Collection
    {
        $sold = Cache::remember('home:best-sellers', now()->addMinutes(30), fn () => OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q
                ->whereIn('status', OrderStatus::revenueStatuses())
                ->where('created_at', '>=', now()->subDays(90)))
            ->selectRaw('product_id, SUM(quantity) as sold')
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->limit(self::BEST_SELLER_LIMIT * 2)
            ->pluck('sold', 'product_id')
            ->map(fn ($qty) => (int) $qty)
            ->all());

        $products = Product::active()->with('category')->whereIn('id', array_keys($sold))->get()
            ->sortByDesc(fn (Product $p) => $sold[$p->id] ?? 0)
            ->take(self::BEST_SELLER_LIMIT)
            ->values();

        foreach ([
            fn ($q) => $q->where('is_featured', true),
            fn ($q) => $q,
        ] as $filler) {
            if ($products->count() >= self::BEST_SELLER_LIMIT) {
                break;
            }

            $products = $products->concat(
                Product::active()->with('category')->tap($filler)
                    ->whereNotIn('id', $products->pluck('id'))
                    ->latest()->take(self::BEST_SELLER_LIMIT - $products->count())->get()
            );
        }

        // Urutan "terlaris" & "terbaru" dipakai untuk pengurutan di sisi browser.
        $newest = $products->sortByDesc(fn (Product $p) => [$p->created_at?->timestamp ?? 0, $p->id])->pluck('id')->flip();

        return $products->values()->each(function (Product $p, int $rank) use ($sold, $newest) {
            $p->setAttribute('sold_count', $sold[$p->id] ?? 0);
            $p->setAttribute('rank_best', $rank);
            $p->setAttribute('rank_new', $newest[$p->id]);
        });
    }
}
