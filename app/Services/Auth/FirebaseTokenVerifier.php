<?php

namespace App\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Memverifikasi ID token Firebase Authentication tanpa service account:
 * cukup Project ID dan sertifikat publik Google (di-cache sesuai Cache-Control).
 *
 * @see https://firebase.google.com/docs/auth/admin/verify-id-tokens#verify_id_tokens_using_a_third-party_jwt_library
 */
class FirebaseTokenVerifier
{
    public const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    private const CACHE_KEY = 'firebase:securetoken-certs';

    /** Toleransi selisih jam server (detik). */
    private const LEEWAY = 60;

    public function __construct(private readonly ?string $projectId) {}

    public function verify(string $idToken): FirebaseIdentity
    {
        if (! $this->projectId) {
            throw new InvalidFirebaseToken('Firebase belum dikonfigurasi.');
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY;

        try {
            $keys = collect($this->certificates())
                ->map(fn (string $cert) => new Key($cert, 'RS256'))
                ->all();

            $claims = JWT::decode($idToken, $keys);
        } catch (InvalidFirebaseToken $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidFirebaseToken('Token Firebase tidak valid: '.$e->getMessage(), previous: $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $now = time();

        if (($claims->aud ?? null) !== $this->projectId
            || ($claims->iss ?? null) !== 'https://securetoken.google.com/'.$this->projectId
            || ! is_string($claims->sub ?? null) || $claims->sub === '' || strlen($claims->sub) > 128
            || ! isset($claims->auth_time) || $claims->auth_time > $now + self::LEEWAY) {
            throw new InvalidFirebaseToken('Klaim token Firebase tidak sesuai.');
        }

        return new FirebaseIdentity(
            uid: $claims->sub,
            email: isset($claims->email) ? mb_strtolower((string) $claims->email) : null,
            emailVerified: (bool) ($claims->email_verified ?? false),
            name: isset($claims->name) ? (string) $claims->name : null,
            provider: $claims->firebase->sign_in_provider ?? null,
        );
    }

    /** @return array<string, string> sertifikat PEM per key id */
    private function certificates(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $response = Http::timeout(10)->get(self::CERTS_URL);

        if (! $response->successful() || ! is_array($certs = $response->json()) || $certs === []) {
            throw new InvalidFirebaseToken('Gagal mengambil sertifikat publik Firebase.');
        }

        preg_match('/max-age=(\d+)/', (string) $response->header('Cache-Control'), $match);
        Cache::put(self::CACHE_KEY, $certs, max(60, (int) ($match[1] ?? 3600)));

        return $certs;
    }
}
