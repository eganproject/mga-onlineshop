<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\FirebaseTokenVerifier;
use App\Services\Auth\InvalidFirebaseToken;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FirebaseLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT = 'mga-test';

    private string $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.firebase.enabled' => true,
            'services.firebase.api_key' => 'AIza-test',
            'services.firebase.project_id' => self::PROJECT,
            'services.firebase.auth_domain' => self::PROJECT.'.firebaseapp.com',
        ]);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $this->privateKey);
        $cert = openssl_csr_sign(openssl_csr_new(['commonName' => 'securetoken'], $key), null, $key, 1);
        openssl_x509_export($cert, $certPem);

        Http::fake([FirebaseTokenVerifier::CERTS_URL => Http::response(['kid-1' => $certPem], 200, ['Cache-Control' => 'public, max-age=3600'])]);
        Mail::fake();
    }

    private function token(array $claims = [], string $kid = 'kid-1', ?string $privateKey = null): string
    {
        $now = time();

        return JWT::encode(array_merge([
            'iss' => 'https://securetoken.google.com/'.self::PROJECT,
            'aud' => self::PROJECT,
            'auth_time' => $now - 5,
            'iat' => $now - 5,
            'exp' => $now + 3600,
            'sub' => 'uid-123',
            'email' => 'Budi@Gmail.com',
            'email_verified' => true,
            'name' => 'Budi Santoso',
            'firebase' => ['sign_in_provider' => 'google.com'],
        ], $claims), $privateKey ?? $this->privateKey, 'RS256', $kid);
    }

    public function test_google_button_shows_only_when_configured(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Lanjutkan dengan Google')->assertSee(route('login.firebase'));
        $this->get(route('register'))->assertOk()->assertSee('Daftar dengan Google');

        config(['services.firebase.enabled' => false]);

        $this->get(route('login'))->assertOk()->assertDontSee('Lanjutkan dengan Google');
        $this->post(route('login.firebase'), ['id_token' => $this->token()])->assertNotFound();
    }

    public function test_new_google_user_becomes_verified_customer(): void
    {
        $this->post(route('login.firebase'), ['id_token' => $this->token()])
            ->assertRedirect(route('account.dashboard'));

        $user = User::where('email', 'budi@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertSame('uid-123', $user->firebase_uid);
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_returning_google_user_logs_into_same_account(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['firebase_uid' => 'uid-123'])->save();

        // Email di Google boleh berubah, akun dicari lewat UID.
        $this->post(route('login.firebase'), ['id_token' => $this->token(['email' => 'baru@gmail.com'])])
            ->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_existing_customer_with_same_email_is_linked(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'budi@gmail.com']);

        $this->post(route('login.firebase'), ['id_token' => $this->token()])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('uid-123', $user->firebase_uid);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_unverified_google_email_is_not_linked_to_existing_account(): void
    {
        User::factory()->create(['email' => 'budi@gmail.com']);

        $this->post(route('login.firebase'), ['id_token' => $this->token(['email_verified' => false])])
            ->assertSessionHasErrors('firebase');

        $this->assertGuest();
    }

    public function test_staff_cannot_login_with_google(): void
    {
        User::factory()->admin()->create(['email' => 'budi@gmail.com']);

        $this->from(route('login'))->post(route('login.firebase'), ['id_token' => $this->token()])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('firebase');

        $this->assertGuest();
    }

    public function test_inactive_customer_cannot_login_with_google(): void
    {
        User::factory()->inactive()->create(['email' => 'budi@gmail.com']);

        $this->post(route('login.firebase'), ['id_token' => $this->token()])->assertSessionHasErrors('firebase');
        $this->assertGuest();
    }

    public function test_email_linked_to_another_google_account_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'budi@gmail.com']);
        $user->forceFill(['firebase_uid' => 'uid-lain'])->save();

        $this->post(route('login.firebase'), ['id_token' => $this->token()])->assertSessionHasErrors('firebase');
        $this->assertGuest();
    }

    public function test_redirects_to_intended_page(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('login'));

        $this->post(route('login.firebase'), ['id_token' => $this->token()])
            ->assertRedirect(route('checkout.index'));
    }

    public function test_invalid_tokens_are_rejected(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($other, $otherPem);

        $bad = [
            'proyek lain' => $this->token(['aud' => 'proyek-lain']),
            'issuer salah' => $this->token(['iss' => 'https://securetoken.google.com/proyek-lain']),
            'kedaluwarsa' => $this->token(['exp' => time() - 3600, 'iat' => time() - 7200]),
            'tanpa sub' => $this->token(['sub' => '']),
            'auth_time di masa depan' => $this->token(['auth_time' => time() + 3600]),
            'kunci asing' => $this->token(privateKey: $otherPem),
            'kid tidak dikenal' => $this->token(kid: 'kid-x'),
            'bukan jwt' => 'abc.def',
        ];

        $verifier = app(FirebaseTokenVerifier::class);

        foreach ($bad as $case => $token) {
            try {
                $verifier->verify($token);
                $this->fail("Token '{$case}' seharusnya ditolak.");
            } catch (InvalidFirebaseToken) {
                $this->addToAssertionCount(1);
            }
        }

        $this->post(route('login.firebase'), ['id_token' => $bad['proyek lain']])->assertSessionHasErrors('firebase');
        $this->assertGuest();
    }

    public function test_public_certificates_are_cached(): void
    {
        $verifier = app(FirebaseTokenVerifier::class);
        $verifier->verify($this->token());
        $verifier->verify($this->token(['sub' => 'uid-456']));

        Http::assertSentCount(1);
    }
}
