<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('shop.home', [
            'categories' => Category::active()->withCount(['products' => fn ($q) => $q->active()])
                ->orderBy('sort_order')->take(8)->get(),
            'featured' => Product::active()->where('is_featured', true)->latest()->take(8)->get(),
            'latest' => Product::active()->latest()->take(8)->get(),
        ]);
    }
}
