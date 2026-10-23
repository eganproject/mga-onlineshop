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
                // Jumlah produk induk termasuk produk di subkategorinya.
                ->each(fn (Category $c) => $c->products_count += $c->children->sum('products_count')),
            'bestSellers' => $bestSellers->forHome(),
            'onSale' => Product::active()->with('category')->onSale()->latest()->take(4)->get(),
            'latestArticles' => Article::published()->with('category:id,name,slug')->latest('published_at')->take(3)->get(),
        ]);
    }
}
