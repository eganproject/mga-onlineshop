<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Menjelajahi semua link internal (seperti pengunjung yang mengklik) untuk tiap peran,
 * memakai data demo lengkap. Setiap link yang tampil harus bisa dibuka: tidak boleh 404, 403, atau 500.
 */
class LinkCrawlTest extends TestCase
{
    use RefreshDatabase;

    /** Jumlah variasi query string per path (paginasi, urutan, filter) yang ikut dijelajahi. */
    private const QUERY_VARIANTS_PER_PATH = 2;

    /** Halaman sejenis (pesanan, pelanggan, id) cukup dicek beberapa contoh saja. */
    private const PAGES_PER_SHAPE = 4;

    private const MAX_PAGES = 600;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_links_are_not_broken(): void
    {
        $this->assertNoBrokenLinks(null, ['/', '/produk', '/artikel', '/masuk', '/daftar', '/keranjang', '/sitemap.xml']);
    }

    public function test_customer_links_are_not_broken(): void
    {
        // Seperti setelah `shop:import-mga --replace`: produk yang pernah dibeli & di-wishlist dinonaktifkan.
        $customer = User::where('email', 'pelanggan@mga.test')->firstOrFail();
        $ordered = Product::whereIn('id', OrderItem::whereIn('order_id', $customer->orders()->select('id'))->select('product_id'))->first();
        $this->assertNotNull($ordered, 'Data demo pelanggan harus punya pesanan.');
        $ordered->forceFill(['is_active' => false])->save();
        $customer->wishlist()->syncWithoutDetaching([$ordered->id]);

        $this->assertNoBrokenLinks('pelanggan@mga.test', ['/', '/produk', '/akun', '/keranjang', '/artikel']);
    }

    public function test_superadmin_links_are_not_broken(): void
    {
        $this->assertNoBrokenLinks('admin@mga.test', ['/admin', '/']);
    }

    public function test_warehouse_staff_links_are_not_broken(): void
    {
        $this->assertNoBrokenLinks('gudang@mga.test', ['/admin']);
    }

    public function test_customer_service_links_are_not_broken(): void
    {
        $this->assertNoBrokenLinks('cs@mga.test', ['/admin']);
    }

    /** @param  list<string>  $start */
    private function assertNoBrokenLinks(?string $email, array $start): void
    {
        if ($email) {
            $this->actingAs(User::where('email', $email)->firstOrFail());
        }

        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $queue = array_map(fn ($path) => [$path, '(awal)'], $start);
        $seen = array_fill_keys($start, true);
        $variants = [];
        $shapes = [];
        $broken = [];
        $visited = 0;

        while ($queue && $visited < self::MAX_PAGES) {
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

            preg_match_all('/<a\b[^>]*\bhref="([^"]+)"/i', (string) $response->getContent(), $matches);

            foreach ($matches[1] as $href) {
                $link = $this->normalize(html_entity_decode($href), $host);

                if ($link === null || isset($seen[$link])) {
                    continue;
                }

                $path = Str::before($link, '?');
                $shape = preg_replace('#/(\d+|MGA-[A-Z0-9-]+)(?=/|$)#', '/*', $path);
                $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
                if ($shape !== $path && $shapes[$shape] > self::PAGES_PER_SHAPE) {
                    continue;
                }

                if (str_contains($link, '?')) {
                    $variants[$path] = ($variants[$path] ?? 0) + 1;
                    if ($variants[$path] > self::QUERY_VARIANTS_PER_PATH) {
                        continue;
                    }
                }

                $seen[$link] = true;
                $queue[] = [$link, $url];
            }
        }

        $this->assertGreaterThan(count($start), $visited, 'Penjelajah tidak menemukan link apa pun.');
        $this->assertSame([], $broken, 'Link rusak ditemukan ('.($email ?? 'tamu').'):'."\n".implode("\n", $broken));
    }

    private function normalize(string $href, ?string $host): ?string
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
