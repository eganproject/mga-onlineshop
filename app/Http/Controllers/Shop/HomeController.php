<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Services\Storefront\BestSellers;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(BestSellers $bestSellers): View
    {
        return view('shop.home', [
            'banners' => Banner::visible()->take(8)->get(),
            'categories' => Category::active()->root()
                ->withCount(['products' => fn ($q) => $q->active()])
                ->with(['children' => fn ($q) => $q->active()->withCount(['products' => fn ($q) => $q->active()])])
                ->orderBy('sort_order')->take(12)->get()
                ->each(function (Category $category) {
                    // Jumlah produk induk termasuk produk di subkategorinya.
                    $category->products_count += $category->children->sum('products_count');
                    // Kategori tanpa gambar memakai foto produk (unggulan dulu) di dalamnya.
                    $category->setAttribute('cover_url', $category->imageUrl() ?? $this->productCover($category));
                }),
            'bestSellers' => $bestSellers->forHome(),
            'onSale' => Product::active()->with('category')->onSale()->latest()->take(4)->get(),
            'latestArticles' => Article::published()->with('category:id,name,slug')->latest('published_at')->take(3)->get(),
        ]);
    }

    private function productCover(Category $category): ?string
    {
        return Product::active()
            ->whereIn('category_id', [$category->id, ...$category->children->modelKeys()])
            ->whereHas('images')
            ->orderByDesc('is_featured')->orderBy('id')
            ->first()?->imageUrl();
    }
}
