<?php

namespace App\Services\Catalog\Transfer;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Unduh foto produk dari URL di file impor ke galeri produk.
 * Hanya host publik (bukan jaringan internal), maks 5 MB, format jpg/png/webp/gif.
 */
class ImageDownloader
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const TYPES = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    /**
     * @param  list<string>  $urls
     * @return list<string> peringatan untuk URL yang gagal
     */
    public function attach(Product $product, array $urls): array
    {
        $warnings = [];
        $position = (int) $product->images()->max('sort_order') + ($product->images()->exists() ? 1 : 0);

        foreach ($urls as $url) {
            try {
                $path = $this->download($url);
                $product->images()->create(['path' => $path, 'sort_order' => $position++]);
            } catch (Throwable $e) {
                $warnings[] = 'Foto tidak diunduh ('.Str::limit($url, 80).'): '.($e instanceof InvalidCell ? $e->getMessage() : 'gagal mengakses URL.');
            }
        }

        $product->unsetRelation('coverImage')->unsetRelation('images');

        return $warnings;
    }

    private function download(string $url): string
    {
        if (! $this->isPublicUrl($url)) {
            throw new InvalidCell('alamat tidak diizinkan.');
        }

        $response = Http::timeout(15)
            ->withOptions([
                'allow_redirects' => false,
                // Batalkan sebelum mengunduh bila server sudah bilang filenya terlalu besar.
                'on_headers' => function ($response) {
                    if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                        throw new InvalidCell('ukuran lebih dari 5 MB.');
                    }
                },
            ])
            ->withHeaders(['Accept' => 'image/*'])
            ->get($url);

        if (! $response->successful()) {
            throw new InvalidCell('server membalas '.$response->status().'.');
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new InvalidCell('ukuran lebih dari 5 MB.');
        }

        $info = @getimagesizefromstring($body);
        $extension = $info ? (self::TYPES[$info[2]] ?? null) : null;
        if (! $extension) {
            throw new InvalidCell('file bukan gambar JPG/PNG/WEBP/GIF.');
        }

        $path = 'products/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($path, $body);

        return $path;
    }

    /** Tolak localhost / IP privat agar fitur impor tidak bisa dipakai mengintip jaringan internal. */
    public function isPublicUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if (! $ips) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
