<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Reviews\ReviewService;
use App\Support\InfiniteScroll;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProductController extends Controller
{
    public const SORTS = [
        'terbaru' => 'Terbaru',
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
            ->when($minPrice, fn ($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice, fn ($q) => $q->where('price', '<=', $maxPrice))
            ->when($sort === 'terbaru', fn ($q) => $q->latest())
            ->when($sort === 'termurah', fn ($q) => $q->orderBy('price'))
            ->when($sort === 'termahal', fn ($q) => $q->orderByDesc('price'))
            ->when($sort === 'nama', fn ($q) => $q->orderBy('name'))
            ->paginate(12)
            ->withQueryString();

        return InfiniteScroll::respond($request, view('shop.products.index', [
            'products' => $products,
            'categories' => Category::active()->root()
                ->with(['children' => fn ($q) => $q->active()])
                ->orderBy('sort_order')->orderBy('name')->get(),
            'activeCategory' => $category,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
        ]));
    }

    public function show(Request $request, Product $product, ReviewService $reviewService): View|Response
    {
        abort_unless($product->is_active, 404);

        $user = $request->user();

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
                ->latest('approved_at')->latest('id')
                ->paginate(5, pageName: 'ulasan')
                ->fragment('ulasan'),
            'ratingDistribution' => $product->review_count > 0 ? $reviewService->distribution($product) : [],
            'myReview' => $user?->isCustomer() ? $user->reviews()->where('product_id', $product->id)->first() : null,
            'canReview' => $user?->isCustomer() && $reviewService->canReview($user, $product),
        ]));
    }
}
