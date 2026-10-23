<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleCategory;
use App\Support\AdminList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleCategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.article-categories.index', [
            'categories' => $this->list($request),
            'category' => new ArticleCategory(['sort_order' => 0]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ArticleCategory::create($this->validated($request));

        return redirect()->route('admin.article-categories.index')->with('success', 'Kategori artikel ditambahkan.');
    }

    public function edit(Request $request, ArticleCategory $articleCategory): View
    {
        return view('admin.article-categories.index', [
            'categories' => $this->list($request),
            'category' => $articleCategory,
        ]);
    }

    public function update(Request $request, ArticleCategory $articleCategory): RedirectResponse
    {
        $articleCategory->update($this->validated($request, $articleCategory));

        return redirect()->route('admin.article-categories.index')->with('success', 'Kategori artikel diperbarui.');
    }

    public function destroy(ArticleCategory $articleCategory): RedirectResponse
    {
        // Artikelnya tetap ada, hanya jadi tanpa kategori (nullOnDelete).
        $articleCategory->delete();

        return redirect()->route('admin.article-categories.index')->with('success', 'Kategori artikel dihapus.');
    }

    private function validated(Request $request, ?ArticleCategory $category = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('article_categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], ['slug.unique' => 'Slug ini sudah dipakai kategori lain.'], ['name' => 'nama', 'description' => 'deskripsi']);

        $data['sort_order'] ??= 0;

        return $data;
    }

    private function list(Request $request)
    {
        return AdminList::paginate(
            ArticleCategory::withCount('articles')
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->trim().'%'))
                ->orderBy('sort_order')->orderBy('name'),
            $request, 25, key: 'admin.article-categories',
        );
    }
}
