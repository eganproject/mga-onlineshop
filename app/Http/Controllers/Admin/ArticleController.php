<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Product;
use App\Services\Content\ArticleSanitizer;
use App\Services\Content\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public const STATES = ['draft' => 'Draf', 'scheduled' => 'Terjadwal', 'published' => 'Terbit'];

    public function __construct(private readonly ArticleService $articles) {}

    public function index(Request $request): View
    {
        $state = array_key_exists($request->query('status'), self::STATES) ? $request->query('status') : null;

        $articles = Article::query()
            ->with(['category:id,name', 'author:id,name'])
            ->search($request->string('q')->trim()->value() ?: null)
            ->when($request->filled('kategori'), fn ($q) => $q->where('article_category_id', $request->integer('kategori')))
            ->when($state === 'draft', fn ($q) => $q->where(fn ($q) => $q->where('status', ArticleStatus::Draft)->orWhereNull('published_at')))
            ->when($state === 'scheduled', fn ($q) => $q->where('status', ArticleStatus::Published)->where('published_at', '>', now()))
            ->when($state === 'published', fn ($q) => $q->published())
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.articles.index', [
            'articles' => $articles,
            'categories' => ArticleCategory::orderBy('sort_order')->orderBy('name')->pluck('name', 'id'),
            'states' => self::STATES,
            'state' => $state,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Article(['status' => ArticleStatus::Draft]));
    }

    public function store(Request $request): RedirectResponse
    {
        $article = $this->articles->save(new Article, $this->validated($request), $request->user(), $request->file('cover_image'));

        return redirect()->route('admin.articles.edit', $article)->with('success', $this->savedMessage($article));
    }

    public function edit(Article $article): View
    {
        return $this->form($article->load('products:id,name'));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->articles->save($article, $this->validated($request, $article), cover: $request->file('cover_image'), removeCover: $request->boolean('remove_cover_image'));

        return redirect()->route('admin.articles.edit', $article)->with('success', $this->savedMessage($article));
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->articles->delete($article);

        return redirect()->route('admin.articles.index')->with('success', 'Artikel dihapus.');
    }

    /** Unggah gambar dari editor (tombol gambar / tempel / seret). */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(
            ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096']],
            ['image.max' => 'Ukuran gambar maksimal 4 MB.'],
            ['image' => 'gambar'],
        );

        return response()->json(['url' => $this->articles->storeContentImage($request->file('image'))]);
    }

    private function form(Article $article): View
    {
        return view('admin.articles.form', [
            'article' => $article,
            'categories' => ArticleCategory::orderBy('sort_order')->orderBy('name')->pluck('name', 'id'),
            'products' => Product::active()->without('coverImage')->orderBy('name')->pluck('name', 'id'),
            'tagSuggestions' => Article::whereNotNull('tags')->pluck('tags')->flatten()->filter()->unique()->sort()->values(),
        ]);
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:170', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('articles', 'slug')->ignore($article)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:500000'],
            'article_category_id' => ['nullable', Rule::exists('article_categories', 'id')],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'product_ids' => ['nullable', 'array', 'max:8'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=800'],
        ], [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
            'cover_image.dimensions' => 'Lebar gambar sampul minimal 800 px.',
            'product_ids.max' => 'Pilih maksimal 8 produk terkait.',
            'tags.max' => 'Maksimal 10 tag.',
        ], [
            'title' => 'judul', 'excerpt' => 'ringkasan', 'body' => 'isi artikel', 'article_category_id' => 'kategori',
            'published_at' => 'tanggal terbit', 'meta_title' => 'judul SEO', 'meta_description' => 'deskripsi SEO',
            'cover_image' => 'gambar sampul', 'product_ids' => 'produk terkait',
        ]);

        if (app(ArticleSanitizer::class)->isEmpty($data['body'])) {
            throw ValidationException::withMessages(['body' => 'Isi artikel masih kosong.']);
        }

        return $data;
    }

    private function savedMessage(Article $article): string
    {
        return match ($article->state()) {
            'published' => 'Artikel diterbitkan.',
            'scheduled' => 'Artikel dijadwalkan terbit '.$article->published_at->translatedFormat('j M Y, H:i').'.',
            default => 'Draf artikel disimpan.',
        };
    }
}
