<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('shop.home', [
            'banners' => Banner::visible()->take(8)->get(),
            'categories' => Category::active()->root()
                ->withCount(['products' => fn ($q) => $q->active()])
                ->with(['children' => fn ($q) => $q->active()->withCount(['products' => fn ($q) => $q->active()])])
                ->orderBy('sort_order')->take(8)->get()
                // Jumlah produk induk termasuk produk di subkategorinya.
                ->each(fn (Category $c) => $c->products_count += $c->children->sum('products_count')),
            'featured' => Product::active()->with('category')->where('is_featured', true)->latest()->take(8)->get(),
            'latest' => Product::active()->with('category')->latest()->take(8)->get(),
            'onSale' => Product::active()->with('category')->onSale()->latest()->take(4)->get(),
        ]);
    }
}
