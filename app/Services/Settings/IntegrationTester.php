<?php

namespace App\Services\Settings;

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
    public const INTEGRATIONS = ['midtrans', 'rajaongkir', 'firebase'];

    /** @return Result */
    public function test(string $integration): array
    {
        try {
            return match ($integration) {
                'midtrans' => $this->midtrans(),
                'rajaongkir' => $this->rajaongkir(),
                'firebase' => $this->firebase(),
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
            return $this->result(false, 'API key ditolak Firebase: '.$this->apiMessage($response, 'error.message'));
        }

        $notes = [];
        $remoteProject = (string) $response->json('projectId');

        if ($remoteProject !== '' && $remoteProject !== $projectId) {
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
