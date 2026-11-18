<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;

/**
 * Penjelajah link untuk tes: membuka setiap <a href> internal (seperti pengunjung yang mengklik)
 * dan memeriksa setiap tujuan form/aksi (action, formaction, data-*-url) sudah terdaftar dan
 * diizinkan untuk user yang sedang login. Link/aksi yang tampil tapi berujung 404/403/500 = gagal.
 */
trait CrawlsLinks
{
    /** Jumlah variasi query string per path (paginasi, urutan, filter) yang ikut dijelajahi. */
    protected int $crawlQueryVariants = 2;

    /** Halaman sejenis (pesanan, pelanggan, id) cukup dicek beberapa contoh saja. */
    protected int $crawlPagesPerShape = 4;

    protected int $crawlMaxPages = 600;

    /**
     * @param  list<string>  $start
     * @return list<string> daftar masalah
     */
    protected function crawlForBrokenLinks(?User $user, array $start): array
    {
        if ($user) {
            $this->actingAs($user);
        }

        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $queue = array_map(fn ($path) => [$path, '(awal)'], $start);
        $seen = array_fill_keys($start, true);
        $checkedActions = [];
        $variants = [];
        $shapes = [];
        $broken = [];
        $visited = 0;

        while ($queue && $visited < $this->crawlMaxPages) {
            [$url, $from] = array_shift($queue);
            $visited++;

            $response = $this->get($url);
            $status = $response->getStatusCode();

            // 429 = pembatas laju (mis. unduh PDF) bekerja sebagaimana mestinya.
            if ($status >= 400 && $status !== 429) {
                $broken[] = "{$status} {$url} (dari {$from})";

                continue;
            }

            if ($status >= 300 || ! str_contains((string) $response->headers->get('Content-Type'), 'html')) {
                continue;
            }

            $html = (string) $response->getContent();

            foreach ($this->actionTargets($html, $host) as [$method, $target]) {
                $key = $method.' '.preg_replace('#/(\d+|MGA-[A-Z0-9-]+)(?=/|$)#', '/*', $target);
                if (isset($checkedActions[$key])) {
                    continue;
                }
                $checkedActions[$key] = true;

                if ($problem = $this->actionProblem($method, $target, $user)) {
                    $broken[] = "{$problem}: {$method} {$target} (di {$url})";
                }
            }

            preg_match_all('/<a\b[^>]*\bhref="([^"]+)"/i', $html, $matches);

            foreach ($matches[1] as $href) {
                $link = $this->normalizeCrawlUrl(html_entity_decode($href), $host);

                if ($link === null || isset($seen[$link])) {
                    continue;
                }

                $path = Str::before($link, '?');
                $shape = preg_replace('#/(\d+|MGA-[A-Z0-9-]+)(?=/|$)#', '/*', $path);
                $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
                if ($shape !== $path && $shapes[$shape] > $this->crawlPagesPerShape) {
                    continue;
                }

                if (str_contains($link, '?')) {
                    $variants[$path] = ($variants[$path] ?? 0) + 1;
                    if ($variants[$path] > $this->crawlQueryVariants) {
                        continue;
                    }
                }

                $seen[$link] = true;
                $queue[] = [$link, $url];
            }
        }

        $this->assertGreaterThan(count($start), $visited, 'Penjelajah tidak menemukan link apa pun.');

        return $broken;
    }

    /**
     * Tujuan form & aksi JS di halaman: [metode, path]. Metode `*` = tidak diketahui (data-*-url).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function actionTargets(string $html, ?string $host): array
    {
        $targets = [];

        preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $html, $forms, PREG_SET_ORDER);
        foreach ($forms as [, $attributes, $body]) {
            if (! preg_match('/\baction="([^"]*)"/i', $attributes, $action)) {
                continue;
            }
            $method = preg_match('/\bmethod="([^"]+)"/i', $attributes, $m) ? strtoupper($m[1]) : 'GET';
            if ($method === 'POST' && preg_match('/name="_method"[^>]*value="([^"]+)"|value="([^"]+)"[^>]*name="_method"/i', $body, $spoof)) {
                $method = strtoupper($spoof[1] ?: $spoof[2]);
            }
            $targets[] = [$method, html_entity_decode($action[1])];

            preg_match_all('/\bformaction="([^"]+)"/i', $body, $formactions);
            foreach ($formactions[1] as $formaction) {
                $targets[] = [$method === 'GET' ? 'GET' : 'POST', html_entity_decode($formaction)];
            }
        }

        preg_match_all('/\bdata-[a-z-]*url="([^"]+)"/i', $html, $dataUrls);
        foreach ($dataUrls[1] as $url) {
            $targets[] = ['*', html_entity_decode($url)];
        }

        // URL absolut lain di halaman (konfigurasi Alpine/JSON, mis. endpoint fetch) — metodenya tidak diketahui.
        if ($host) {
            preg_match_all('#https?:(?:\\\\?/){2}'.preg_quote($host, '#').'(?::\d+)?((?:\\\\?/[A-Za-z0-9._~%-]*)+)#', $html, $absolute);
            foreach ($absolute[1] as $path) {
                $targets[] = ['*', str_replace('\\/', '/', $path)];
            }
        }

        $result = [];
        foreach ($targets as [$method, $target]) {
            // Template JS (mis. ".../__ID__") dan link luar tidak dicek.
            if ($target === '' || str_contains($target, '__') || preg_match('/[{}\s]|\'/', $target)) {
                continue;
            }
            $parts = parse_url($target);
            if ($parts === false || (isset($parts['host']) && $parts['host'] !== $host) || isset($parts['scheme']) && ! in_array($parts['scheme'], ['http', 'https'], true)) {
                continue;
            }
            $path = '/'.ltrim($parts['path'] ?? '/', '/');
            if (preg_match('#^/(storage|build|images|fonts|vendor)/#', $path) || preg_match('/\.(png|jpe?g|webp|svg|gif|ico|css|js|xml|txt|woff2?|webmanifest)$/i', $path)) {
                continue;
            }
            $result[] = [$method, $path];
        }

        return $result;
    }

    /** Masalah pada tujuan aksi: route tidak ada, atau middleware `can:` menolak user ini. */
    private function actionProblem(string $method, string $path, ?User $user): ?string
    {
        $methods = $method === '*' ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : [$method];
        $routes = [];
        foreach ($methods as $verb) {
            try {
                $route = Router::getRoutes()->match(Request::create($path, $verb));
            } catch (\Throwable) {
                continue;
            }
            if (! $route->isFallback) {
                $routes[] = $route;
            }
        }

        if (! $routes) {
            return 'Tujuan aksi tidak terdaftar';
        }

        foreach ($routes as $route) {
            if ($this->routeAllows($route, $user)) {
                return null;
            }
        }

        return 'Aksi tampil tapi tidak diizinkan (403)';
    }

    private function routeAllows(Route $route, ?User $user): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }
            // `auth` untuk tamu = diarahkan ke halaman masuk (bukan jalan buntu); role/can dicek hanya bila login.
            if (! $user && ($middleware === 'auth' || str_starts_with($middleware, 'role:') || str_starts_with($middleware, 'can:'))) {
                return true;
            }
            if ($middleware === 'guest' && $user) {
                return false;
            }
            if (str_starts_with($middleware, 'role:') && (! $user || $user->role->value !== Str::after($middleware, 'role:'))) {
                return false;
            }
            if (str_starts_with($middleware, 'can:')) {
                if (! $user || ! Gate::forUser($user)->allows(Str::after($middleware, 'can:'))) {
                    return false;
                }
            }
        }

        return true;
    }

    private function normalizeCrawlUrl(string $href, ?string $host): ?string
    {
        $href = Str::before($href, '#');

        if ($href === '' || preg_match('/[\\\\{}\s]|\'/', $href) || preg_match('/^(mailto|tel|javascript|data|whatsapp):/i', $href)) {
            return null;
        }

        $parts = parse_url($href);
        if ($parts === false || (isset($parts['host']) && $parts['host'] !== $host)) {
            return null;
        }

        $path = '/'.ltrim($parts['path'] ?? '/', '/');

        // Gambar/berkas statis bukan halaman aplikasi.
        if (preg_match('#^/(storage|build|images|fonts)/#', $path) || preg_match('/\.(png|jpe?g|webp|svg|gif|ico|css|js)$/i', $path)) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
