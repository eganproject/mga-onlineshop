<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Support\InfiniteScroll;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View|Response
    {
        $category = $request->filled('kategori')
            ? ArticleCategory::where('slug', $request->string('kategori'))->first()
            : null;
        $tag = $request->string('tag')->squish()->lower()->value() ?: null;
        $search = $request->string('q')->trim()->value() ?: null;

        $articles = Article::published()
            ->with('category:id,name,slug')
            ->when($category, fn ($q) => $q->whereBelongsTo($category, 'category'))
            ->withTag($tag)
            ->search($search)
            ->latest('published_at')->latest('id')
            ->paginate(9)
            ->withQueryString();

        return InfiniteScroll::respond($request, view('shop.articles.index', [
            'articles' => $articles,
            'categories' => ArticleCategory::whereHas('articles', fn ($q) => $q->published())->orderBy('sort_order')->orderBy('name')->get(),
            'activeCategory' => $category,
            'tag' => $tag,
            'search' => $search,
        ]));
    }

    public function show(Request $request, Article $article): View
    {
        // Draf & artikel terjadwal hanya bisa dipratinjau staf yang mengelola artikel.
        $preview = ! $article->isVisible();
        abort_if($preview && ! $request->user()?->can('articles.manage'), 404);

        $article->load(['category', 'author:id,name', 'products' => fn ($q) => $q->active()->with('category')]);

        return view('shop.articles.show', [
            'article' => $article,
            'preview' => $preview,
            'related' => Article::published()
                ->with('category:id,name,slug')
                ->whereKeyNot($article->id)
                ->when($article->article_category_id, fn ($q) => $q->orderByRaw('article_category_id = ? desc', [$article->article_category_id]))
                ->latest('published_at')
                ->take(3)
                ->get(),
        ]);
    }
}
