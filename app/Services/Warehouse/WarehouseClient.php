<?php

namespace App\Services\Warehouse;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Klien API stok warehouse29 (docs/API_STOK_GUDANG.md di repo warehouse29):
 * GET /api/v1/health dan GET /api/v1/stocks, token Bearer, allowlist IP,
 * rate limit 60/menit (429 + Retry-After).
 */
class WarehouseClient
{
    public const MAX_PER_PAGE = 500;

    /** Jeda maksimal (detik) yang mau ditunggu saat kena rate limit. */
    private const MAX_RETRY_WAIT = 30;

    public function isConfigured(): bool
    {
        return filled($this->baseUrl()) && filled(config('services.warehouse.token'));
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        return $this->get('/health');
    }

    /**
     * Satu halaman stok. Parameter: updated_since, updated_until (ISO-8601), page, per_page.
     *
     * @return array{meta: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function stocks(array $query): array
    {
        $body = $this->get('/stocks', array_filter($query, fn ($v) => $v !== null && $v !== ''));

        if (! is_array($body['data'] ?? null) || ! is_array($body['meta'] ?? null)) {
            throw new WarehouseApiException('Jawaban API gudang tidak sesuai format (data/meta tidak ada).');
        }

        return ['meta' => $body['meta'], 'data' => array_values($body['data'])];
    }

    /** "https://gudang.example.com/" atau ".../api/v1" → "https://gudang.example.com/api/v1". */
    public function baseUrl(): ?string
    {
        $url = trim((string) config('services.warehouse.base_url'));

        if ($url === '') {
            return null;
        }

        return preg_replace('#/api/v1$#', '', rtrim($url, '/')).'/api/v1';
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $query = [], bool $retry = true): array
    {
        if (! $this->isConfigured()) {
            throw new WarehouseApiException('URL atau token warehouse29 belum diisi (Admin › Integrasi).');
        }

        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException) {
            throw new WarehouseApiException('Server toko tidak bisa terhubung ke '.$this->host().'. Cek URL dan koneksi server.');
        }

        if ($response->status() === 429 && $retry) {
            $wait = (int) $response->header('Retry-After');

            if ($wait > 0 && $wait <= self::MAX_RETRY_WAIT) {
                sleep($wait);

                return $this->get($path, $query, false);
            }
        }

        if (! $response->successful() || $response->json('success') === false) {
            throw new WarehouseApiException($this->errorMessage($response));
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new WarehouseApiException('Jawaban API gudang bukan JSON. Pastikan URL mengarah ke aplikasi warehouse29.');
        }

        return $body;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken((string) config('services.warehouse.token'))
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(max(5, (int) config('services.warehouse.timeout', 20)));
    }

    private function errorMessage(Response $response): string
    {
        $code = (string) $response->json('error.code');
        $message = Str::limit((string) ($response->json('error.message') ?: 'HTTP '.$response->status()), 160);

        return match (true) {
            $code === 'INVALID_TOKEN' || $response->status() === 401 => "Token ditolak gudang: {$message} Samakan dengan STOCK_API_TOKEN di server warehouse29.",
            $code === 'FORBIDDEN' && str_contains($message, 'IP') => "{$message} Tambahkan IP server toko di warehouse29 › Master Data › Akses API.",
            $code === 'FORBIDDEN' => "{$message} Aktifkan STOCK_API_ENABLED di server warehouse29.",
            $code === 'RATE_LIMIT_EXCEEDED' || $response->status() === 429 => 'Terlalu banyak permintaan ke gudang (rate limit). Coba lagi sebentar lagi.',
            $response->status() === 404 => 'Alamat API tidak ditemukan (404). Cek URL warehouse29.',
            default => "Gudang menjawab: {$message}",
        };
    }

    private function host(): string
    {
        return (string) (parse_url((string) $this->baseUrl(), PHP_URL_HOST) ?: 'server gudang');
    }
}
