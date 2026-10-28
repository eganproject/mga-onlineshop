<?php

namespace App\Services\Settings;

use App\Services\Warehouse\WarehouseApiException;
use App\Services\Warehouse\WarehouseClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Tombol "Tes koneksi" di Admin › Integrasi. Memakai kunci yang sedang
 * berlaku (database atau .env) dan hanya memanggil endpoint baca yang ringan.
 *
 * @phpstan-type Result array{ok: bool, message: string, notes: list<string>}
 */
class IntegrationTester
{
    public const INTEGRATIONS = ['midtrans', 'rajaongkir', 'firebase', 'warehouse'];

    /** @return Result */
    public function test(string $integration): array
    {
        try {
            return match ($integration) {
                'midtrans' => $this->midtrans(),
                'rajaongkir' => $this->rajaongkir(),
                'firebase' => $this->firebase(),
                'warehouse' => $this->warehouse(),
            };
        } catch (ConnectionException) {
            return $this->result(false, 'Server tidak bisa terhubung ke layanan ini. Cek koneksi internet server lalu coba lagi.');
        }
    }

    /**
     * Cek status pesanan acak lewat Core API: kunci yang benar dijawab
     * "transaksi tidak ditemukan" (404), kunci salah dijawab 401.
     *
     * @return Result
     */
    private function midtrans(): array
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $clientKey = (string) config('services.midtrans.client_key');
        $production = (bool) config('services.midtrans.is_production');

        if ($serverKey === '') {
            return $this->result(false, 'Server key belum diisi.');
        }

        $notes = [];
        $sandboxKey = fn (string $key) => Str::startsWith($key, 'SB-');

        if ($production && $sandboxKey($serverKey)) {
            $notes[] = 'Mode Production tetapi server key berawalan "SB-" (kunci Sandbox).';
        } elseif (! $production && $serverKey !== '' && ! $sandboxKey($serverKey)) {
            $notes[] = 'Mode Sandbox tetapi server key tidak berawalan "SB-". Pastikan kuncinya dari dashboard Sandbox.';
        }

        if ($clientKey === '') {
            $notes[] = 'Client key belum diisi; halaman bayar Snap butuh client key.';
        } elseif ($sandboxKey($clientKey) !== $sandboxKey($serverKey)) {
            $notes[] = 'Client key dan server key berasal dari lingkungan berbeda (Sandbox vs Production).';
        }

        $base = $production ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->timeout(10)
            ->get($base.'/v2/'.rawurlencode('MGA-TES-'.Str::upper(Str::random(10))).'/status');

        $code = (string) ($response->json('status_code') ?? $response->status());
        $mode = $production ? 'Production' : 'Sandbox';

        return match (true) {
            in_array($code, ['404', '200', '201'], true) => $this->result(true, "Server key diterima Midtrans ({$mode}).", $notes),
            $code === '401' => $this->result(false, "Server key ditolak Midtrans ({$mode}). Cek kunci dan modenya.", $notes),
            default => $this->result(false, 'Midtrans menjawab: '.$this->apiMessage($response, 'status_message'), $notes),
        };
    }

    /** @return Result */
    private function rajaongkir(): array
    {
        $apiKey = (string) config('services.rajaongkir.api_key');

        if ($apiKey === '') {
            return $this->result(false, 'API key belum diisi.');
        }

        $response = Http::baseUrl((string) config('services.rajaongkir.base_url'))
            ->withHeaders(['key' => $apiKey])
            ->acceptJson()
            ->timeout(15)
            ->get('/destination/province');

        $notes = blank(config('shop.shipping.origin_district_id'))
            ? ['Kecamatan asal pengiriman belum dipilih (Pengaturan Toko).']
            : [];

        if ($response->successful() && is_array($rows = $response->json('data')) && $rows !== []) {
            return $this->result(true, 'API key diterima RajaOngkir ('.count($rows).' provinsi terbaca).', $notes);
        }

        if (in_array($response->status(), [401, 403], true)) {
            return $this->result(false, 'API key ditolak RajaOngkir: '.$this->apiMessage($response, 'meta.message'), $notes);
        }

        return $this->result(false, 'RajaOngkir menjawab: '.$this->apiMessage($response, 'meta.message'), $notes);
    }

    /**
     * Baca konfigurasi project dari Identity Toolkit (endpoint yang juga dipakai
     * Firebase JS SDK): memastikan API key valid, cocok dengan Project ID, dan
     * domain toko sudah diizinkan.
     *
     * @return Result
     */
    private function firebase(): array
    {
        $apiKey = (string) config('services.firebase.api_key');
        $projectId = (string) config('services.firebase.project_id');

        if ($apiKey === '' || $projectId === '') {
            return $this->result(false, 'API key dan Project ID wajib diisi.');
        }

        $appUrl = (string) config('app.url');
        $response = Http::acceptJson()
            ->withHeaders(['Referer' => rtrim($appUrl, '/').'/'])
            ->timeout(10)
            ->get('https://identitytoolkit.googleapis.com/v1/projects', ['key' => $apiKey]);

        if (! $response->successful()) {
            $error = (string) $response->json('error.message');

            // Kode dari Firebase yang sering muncul saat setup, diterjemahkan ke langkah perbaikannya.
            $hint = match (true) {
                str_starts_with($error, 'CONFIGURATION_NOT_FOUND') => 'Authentication belum diaktifkan di project ini. Buka Firebase › Build › Authentication › Get started, lalu aktifkan provider Google. Bila sudah, pastikan API key berasal dari project yang sama.',
                str_contains($error, 'API key not valid') => 'API key tidak dikenal. Salin ulang Web API Key dari Firebase › Project settings › General.',
                str_contains($error, 'referer') || str_contains($error, 'Referer') => 'API key dibatasi HTTP referrer. Tambahkan '.rtrim($appUrl, '/').'/* di Google Cloud Console › Credentials.',
                default => null,
            };

            return $this->result(false, 'API key ditolak Firebase: '.$this->apiMessage($response, 'error.message').($hint ? ". {$hint}" : ''));
        }

        $notes = [];
        $remoteProject = (string) $response->json('projectId');

        // Endpoint ini mengembalikan nomor project (angka), bukan Project ID; hanya bandingkan bila berupa ID.
        if ($remoteProject !== '' && ! ctype_digit($remoteProject) && $remoteProject !== $projectId) {
            return $this->result(false, "API key ini milik project \"{$remoteProject}\", bukan \"{$projectId}\".");
        }

        $host = parse_url($appUrl, PHP_URL_HOST);
        $domains = (array) $response->json('authorizedDomains', []);

        if ($host && $domains !== [] && ! in_array($host, $domains, true)) {
            $notes[] = "Domain {$host} belum ada di Authentication › Settings › Authorized domains.";
        }

        if (blank(config('services.firebase.app_id'))) {
            $notes[] = 'App ID belum diisi (opsional, tetapi disarankan).';
        }

        if (! config('services.firebase.login_enabled')) {
            $notes[] = 'Tombol login Google sedang dimatikan.';
        }

        return $this->result(true, "API key valid untuk project {$projectId}.", $notes);
    }

    /**
     * Panggil /api/v1/health warehouse29: memastikan URL, token, dan allowlist IP benar.
     *
     * @return Result
     */
    private function warehouse(): array
    {
        $client = app(WarehouseClient::class);

        if (! $client->isConfigured()) {
            return $this->result(false, 'URL dan token warehouse29 wajib diisi.');
        }

        try {
            $health = $client->health();
        } catch (WarehouseApiException $e) {
            return $this->result(false, $e->getMessage());
        }

        $notes = [];

        if (! config('services.warehouse.enabled')) {
            $notes[] = 'Sinkron otomatis sedang dimatikan. Stok tetap bisa ditarik manual di Stok › Sinkron gudang.';
        }

        if (str_starts_with((string) $client->baseUrl(), 'http://')) {
            $notes[] = 'URL masih http://. Pakai https:// agar token tidak terkirim tanpa enkripsi.';
        }

        $code = is_string($health['warehouse_code'] ?? null) ? $health['warehouse_code'] : null;

        return $this->result(true, 'Terhubung ke API stok warehouse29'.($code ? " (gudang {$code})" : '').'.', $notes);
    }

    private function apiMessage(Response $response, string $path): string
    {
        $message = $response->json($path);

        return Str::limit(is_string($message) && $message !== '' ? $message : 'HTTP '.$response->status(), 160);
    }

    /** @return Result */
    private function result(bool $ok, string $message, array $notes = []): array
    {
        return ['ok' => $ok, 'message' => $message, 'notes' => array_values($notes)];
    }
}
