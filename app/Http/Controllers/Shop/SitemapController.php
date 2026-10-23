<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $products = Product::active()
            ->without('coverImage')
            ->with('images:id,product_id,path')
            ->latest('updated_at')
            ->get(['id', 'name', 'slug', 'updated_at']);

        $categories = Category::active()->orderBy('sort_order')->get(['id', 'slug', 'updated_at']);

        $articles = Article::published()->latest('published_at')->get(['id', 'title', 'slug', 'cover_image_path', 'published_at', 'updated_at']);

        return response()
            ->view('seo.sitemap', [
                'products' => $products,
                'categories' => $categories,
                'articles' => $articles,
                'lastModified' => $products->max('updated_at'),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
