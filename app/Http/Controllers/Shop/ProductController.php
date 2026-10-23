<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public const SORTS = [
        'terbaru' => 'Terbaru',
        'termurah' => 'Harga terendah',
        'termahal' => 'Harga tertinggi',
        'nama' => 'Nama A-Z',
    ];

    public function index(Request $request): View
    {
        $category = $request->filled('kategori')
            ? Category::active()->where('slug', $request->string('kategori'))->first()
            : null;

        $sort = array_key_exists($request->query('urut'), self::SORTS) ? $request->query('urut') : 'terbaru';

        $products = Product::active()
            ->with('category')
            ->search($request->string('q')->trim()->value() ?: null)
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($request->boolean('tersedia'), fn ($q) => $q->where('stock', '>', 0))
            ->when($sort === 'terbaru', fn ($q) => $q->latest())
            ->when($sort === 'termurah', fn ($q) => $q->orderBy('price'))
            ->when($sort === 'termahal', fn ($q) => $q->orderByDesc('price'))
            ->when($sort === 'nama', fn ($q) => $q->orderBy('name'))
            ->paginate(12)
            ->withQueryString();

        return view('shop.products.index', [
            'products' => $products,
            'categories' => Category::active()->orderBy('sort_order')->get(),
            'activeCategory' => $category,
            'sort' => $sort,
            'sorts' => self::SORTS,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        return view('shop.products.show', [
            'product' => $product->load('category'),
            'related' => Product::active()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->inRandomOrder()
                ->take(4)
                ->get(),
        ]);
    }
}
