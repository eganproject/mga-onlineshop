<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Data pendukung halaman error. Semua aman dipanggil walau database/sesi bermasalah:
 * kalau gagal, hasilnya kosong dan halaman error tetap tampil.
 */
class ErrorPage
{
    /** Kata yang tidak berguna sebagai kata kunci pencarian (sisa URL WordPress, dsb.). */
    private const IGNORED_WORDS = [
        'produk', 'product', 'products', 'product-category', 'kategori', 'category', 'shop', 'toko',
        'artikel', 'blog', 'tag', 'page', 'index', 'html', 'php', 'amp', 'feed', 'www',
    ];

    /**
     * Tebakan kata kunci dari URL yang tidak ditemukan, mis.
     * "/product/x-banner-rangka-60x160/" → "x banner rangka 60x160".
     */
    public static function searchSuggestion(?Request $request = null): string
    {
        try {
            $request ??= request();
            $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));
            $slug = (string) end($segments);
        } catch (Throwable) {
            return '';
        }

        $slug = preg_replace('/\.(html?|php|aspx?)$/i', '', urldecode($slug));
        $words = collect(preg_split('/[-_+\s]+/', Str::lower($slug)))
            ->map(fn ($word) => preg_replace('/[^\p{L}\p{N}]/u', '', $word))
            ->filter(fn ($word) => $word !== '' && ! ctype_digit($word) && ! in_array($word, self::IGNORED_WORDS, true))
            ->take(4);

        return Str::limit($words->implode(' '), 60, '');
    }

    /**
     * Kategori utama untuk jalan pintas di halaman 404.
     *
     * @return array<int, array{name: string, url: string}>
     */
    public static function categories(int $limit = 6): array
    {
        try {
            return Category::query()->active()->root()
                ->orderBy('sort_order')->orderBy('name')
                ->limit($limit)
                ->get(['name', 'slug'])
                ->map(fn (Category $category) => [
                    'name' => $category->name,
                    'url' => route('products.index', ['kategori' => $category->slug]),
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
