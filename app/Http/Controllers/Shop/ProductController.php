<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Reviews\ReviewService;
use App\Support\InfiniteScroll;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    public const SORTS = [
        'terbaru' => 'Terbaru',
        'terlaris' => 'Terlaris',
        'rating' => 'Rating tertinggi',
        'termurah' => 'Harga terendah',
        'termahal' => 'Harga tertinggi',
        'nama' => 'Nama A-Z',
    ];

    public function index(Request $request): View|Response
    {
        $category = $request->filled('kategori')
            ? Category::active()->with('parent')->where('slug', $request->string('kategori'))->first()
            : null;

        $minPrice = $request->integer('harga_min') ?: null;
        $maxPrice = $request->integer('harga_max') ?: null;

        $sort = array_key_exists($request->query('urut'), self::SORTS) ? $request->query('urut') : 'terbaru';

        $products = Product::active()
            ->with('category')
            ->search($request->string('q')->trim()->value() ?: null)
            ->when($category, fn ($q) => $q->whereIn('category_id', $category->familyIds()))
            ->when($request->boolean('tersedia'), fn ($q) => $q->where('stock', '>', 0))
            ->when($request->boolean('promo'), fn ($q) => $q->onSale())
            ->when($request->boolean('grosir'), fn ($q) => $q->wholesale())
            ->when($minPrice, fn ($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice, fn ($q) => $q->where('price', '<=', $maxPrice))
            ->when($sort === 'terbaru', fn ($q) => $q->latest())
            ->when($sort === 'terlaris', fn ($q) => $q->orderByDesc('sold_count')->orderByDesc('rating_avg')->latest())
            ->when($sort === 'rating', fn ($q) => $q->orderByDesc('rating_avg')->orderByDesc('review_count')->orderByDesc('sold_count'))
            ->when($sort === 'termurah', fn ($q) => $q->orderBy('price'))
            ->when($sort === 'termahal', fn ($q) => $q->orderByDesc('price'))
            ->when($sort === 'nama', fn ($q) => $q->orderBy('name'))
            ->paginate(12)
            ->withQueryString();

        return InfiniteScroll::respond($request, view('shop.products.index', [
            'products' => $products,
            'categories' => $this->filterCategories(),
            'activeCategory' => $category,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
        ]));
    }

    /**
     * Kategori induk + subkategori aktif untuk panel filter, lengkap dengan jumlah produk aktif
     * (jumlah induk = produknya sendiri + produk di subkategorinya).
     */
    private function filterCategories(): Collection
    {
        $counts = Product::active()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        return Category::active()->root()
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->each(function (Category $category) use ($counts) {
                $category->children->each(fn (Category $child) => $child->product_count = (int) ($counts[$child->id] ?? 0));
                $category->product_count = (int) ($counts[$category->id] ?? 0) + $category->children->sum('product_count');
            });
    }

    public function show(Request $request, Product $product, ReviewService $reviewService): View|Response
    {
        $user = $request->user();

        abort_unless($product->isViewableBy($user), 404);
        $star = in_array($request->integer('bintang'), range(1, 5), true) ? $request->integer('bintang') : null;

        return InfiniteScroll::respond($request, view('shop.products.show', [
            'product' => $product->load(['category.parent', 'images', 'activeVariants.image']),
            'related' => Product::active()
                ->where('category_id', $product->category_id)
                ->with('category')
                ->whereKeyNot($product->id)
                ->inRandomOrder()
                ->take(4)
                ->get(),
            'reviews' => $product->reviews()->approved()->with('user:id,name')
                ->when($star, fn ($q) => $q->where('rating', $star))
                ->latest('approved_at')->latest('id')
                ->paginate(5, pageName: 'ulasan')
                ->withQueryString()
                ->fragment('ulasan'),
            'starFilter' => $star,
            'ratingDistribution' => $product->review_count > 0 ? $reviewService->distribution($product) : [],
            'myReview' => $user?->isCustomer() ? $user->reviews()->where('product_id', $product->id)->first() : null,
            'canReview' => $user?->isCustomer() && $reviewService->canReview($user, $product),
        ]));
    }
}
