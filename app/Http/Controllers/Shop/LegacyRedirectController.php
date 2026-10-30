<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Support\ErrorPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Rute cadangan untuk URL yang tidak terdaftar. Domain ini sebelumnya situs WordPress
 * (WooCommerce), jadi link lama yang masih ada di Google/bookmark dialihkan (301) ke
 * halaman baru yang paling cocok. Selain itu: halaman 404 biasa.
 */
class LegacyRedirectController extends Controller
{
    /** Jalur WordPress yang tidak perlu dicari padanannya (bot/scanner). */
    private const IGNORED = ['wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login.php', 'xmlrpc.php', 'feed', 'comments'];

    public function __invoke(Request $request): RedirectResponse
    {
        $segments = array_values(array_filter(
            explode('/', Str::lower(trim(rawurldecode($request->path()), '/'))),
            fn ($segment) => $segment !== '',
        ));

        abort_if($segments === [] || $this->ignored($segments), 404);

        $target = $this->target($segments, $request);

        abort_unless($target, 404);

        return redirect()->to($target, 301);
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function target(array $segments, Request $request): ?string
    {
        $first = $segments[0];
        $last = end($segments);
        $search = ErrorPage::searchSuggestion($request);

        return match (true) {
            // /shop, /toko, /store → katalog
            count($segments) === 1 && in_array($first, ['shop', 'toko', 'store', 'products', 'katalog'], true) => route('products.index'),

            // /product/{slug} (WooCommerce) → produk dengan slug sama, atau hasil pencarian
            in_array($first, ['product', 'products', 'shop', 'toko', 'store'], true) => $this->productUrl($last)
                ?? route('products.index', array_filter(['q' => $search])),

            // /product-category/{induk}/{anak} → kategori yang sama
            in_array($first, ['product-category', 'kategori-produk', 'kategori'], true) => $this->categoryUrl($segments)
                ?? route('products.index', array_filter(['q' => $search])),

            // /product-tag/{tag}
            $first === 'product-tag' => route('products.index', array_filter(['q' => $search])),

            in_array($first, ['cart', 'keranjang'], true) => route('cart.index'),
            $first === 'checkout' => route('cart.index'),
            in_array($first, ['my-account', 'account', 'akun-saya'], true) => route('login'),
            in_array($first, ['blog', 'news', 'berita', 'category', 'tag'], true) => $this->articleUrl($last) ?? route('articles.index'),

            // /{slug} atau /2024/05/{slug} (halaman/pos WordPress): produk atau artikel dengan slug sama
            default => $this->productUrl($last) ?? $this->articleUrl($last),
        };
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function ignored(array $segments): bool
    {
        return in_array($segments[0], self::IGNORED, true)
            || in_array(end($segments), ['feed', 'amp'], true)
            || Str::endsWith(end($segments), ['.php', '.xml', '.txt', '.env', '.js', '.css', '.map']);
    }

    private function productUrl(string $slug): ?string
    {
        $product = Product::query()->active()->where('slug', $slug)->first(['id', 'slug']);

        return $product ? route('products.show', $product) : null;
    }

    private function articleUrl(string $slug): ?string
    {
        $article = Article::query()->published()->where('slug', $slug)->first(['id', 'slug']);

        return $article ? route('articles.show', $article) : null;
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function categoryUrl(array $segments): ?string
    {
        foreach (array_reverse(array_slice($segments, 1)) as $slug) {
            if (Category::query()->active()->where('slug', $slug)->exists()) {
                return route('products.index', ['kategori' => $slug]);
            }
        }

        return null;
    }
}
