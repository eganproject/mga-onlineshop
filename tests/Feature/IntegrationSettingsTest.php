<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Contracts\ShippingProvider;
use App\Enums\StaffRole;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payment\MidtransPaymentGateway;
use App\Services\Settings\StoreSettings;
use App\Services\Shipping\RajaOngkirShippingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->admin()->create();
    }

    public function test_only_superadmin_sees_and_opens_integrations(): void
    {
        $this->actingAs($this->superadmin)->get(route('admin.integrations.index'))
            ->assertOk()
            ->assertSee('Tes koneksi')
            ->assertSee('Server key');
        $this->get(route('admin.dashboard'))->assertSee(route('admin.integrations.index'), false);
        $this->assertSame('Superadmin', StaffRole::Owner->label());

        foreach ([StaffRole::Admin, StaffRole::Warehouse, StaffRole::CustomerService] as $role) {
            $this->actingAs(User::factory()->staff($role)->create());

            $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.integrations.index'), false);
            $this->get(route('admin.integrations.index'))->assertForbidden();
            $this->put(route('admin.integrations.update', 'midtrans'), ['midtrans' => ['driver' => 'midtrans', 'server_key' => 'x']])->assertForbidden();
            $this->post(route('admin.integrations.test', 'rajaongkir'))->assertForbidden();
        }

        $this->assertDatabaseCount('settings', 0);

        $this->actingAs(User::factory()->create())->get(route('admin.integrations.index'))->assertForbidden();
    }

    public function test_midtrans_keys_are_saved_encrypted_and_never_shown_or_logged(): void
    {
        $this->actingAs($this->superadmin)->put(route('admin.integrations.update', 'midtrans'), ['midtrans' => [
            'driver' => 'midtrans',
            'is_production' => '0',
            'merchant_id' => 'G123456789',
            'client_key' => 'SB-Mid-client-PUBLIC',
            'server_key' => 'SB-Mid-server-SUPERSECRET1234',
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('midtrans', config('shop.payment.driver'));
        $this->assertSame('SB-Mid-server-SUPERSECRET1234', config('services.midtrans.server_key'));
        $this->assertSame('G123456789', config('services.midtrans.merchant_id'));
        $this->assertInstanceOf(MidtransPaymentGateway::class, app(PaymentGateway::class));

        // Di database terenkripsi, tetapi terbaca lagi saat boot berikutnya.
        $raw = Setting::find('services.midtrans.server_key')->value;
        $this->assertStringNotContainsString('SUPERSECRET', $raw);
        config(['services.midtrans.server_key' => null]);
        app(StoreSettings::class)->apply();
        $this->assertSame('SB-Mid-server-SUPERSECRET1234', config('services.midtrans.server_key'));

        // Halaman hanya menampilkan versi tersamar.
        $this->get(route('admin.integrations.index'))
            ->assertOk()
            ->assertDontSee('SUPERSECRET')
            ->assertSee('SB-M••••••••1234');

        $log = ActivityLog::where('action', 'integrations.update')->sole();
        $this->assertStringNotContainsString('SUPERSECRET', json_encode($log->properties));
        $this->assertSame('diganti (nilai disembunyikan)', $log->properties['perubahan']['Server key']);
        $this->assertSame(['dari' => 'fake', 'menjadi' => 'midtrans'], $log->properties['perubahan']['Driver pembayaran']);
        $this->assertStringNotContainsString('SUPERSECRET', ActivityLog::all()->toJson());
    }

    public function test_blank_secret_keeps_current_key_and_clear_falls_back_to_env(): void
    {
        config(['services.midtrans.server_key' => 'SB-Mid-server-FROMENV']);
        $this->refreshSettingsDefaults();

        $payload = fn (array $extra = []) => ['midtrans' => $extra + [
            'driver' => 'midtrans', 'is_production' => '0', 'client_key' => 'SB-Mid-client-X', 'server_key' => '',
        ]];

        $this->actingAs($this->superadmin)->put(route('admin.integrations.update', 'midtrans'), $payload(['server_key' => 'SB-Mid-server-NEW']))->assertSessionHasNoErrors();
        $this->put(route('admin.integrations.update', 'midtrans'), $payload())->assertSessionHasNoErrors();
        $this->assertSame('SB-Mid-server-NEW', config('services.midtrans.server_key'));

        // Kirim ulang kunci yang sama (tidak ada perubahan): log otomatis pun tidak memuat kuncinya.
        $this->put(route('admin.integrations.update', 'midtrans'), $payload(['server_key' => 'SB-Mid-server-NEW']))->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('SB-Mid-server-NEW', ActivityLog::all()->toJson());

        $this->put(route('admin.integrations.update', 'midtrans'), $payload(['clear_server_key' => '1']))->assertSessionHasNoErrors();
        $this->assertSame('SB-Mid-server-FROMENV', config('services.midtrans.server_key'));
        $this->assertNull(Setting::find('services.midtrans.server_key'));
    }

    public function test_real_driver_requires_keys(): void
    {
        $this->actingAs($this->superadmin)
            ->put(route('admin.integrations.update', 'rajaongkir'), ['rajaongkir' => ['driver' => 'rajaongkir', 'api_key' => '']])
            ->assertSessionHasErrors('rajaongkir.api_key');
        $this->assertSame('fake', config('shop.shipping.driver'));

        $this->put(route('admin.integrations.update', 'rajaongkir'), ['rajaongkir' => ['driver' => 'rajaongkir', 'api_key' => 'ro-key-123']])
            ->assertSessionHasNoErrors();
        $this->assertInstanceOf(RajaOngkirShippingProvider::class, app(ShippingProvider::class));
        $this->assertSame('ro-key-123', config('services.rajaongkir.api_key'));

        $this->put(route('admin.integrations.update', 'midtrans'), ['midtrans' => ['driver' => 'bogus']])
            ->assertSessionHasErrors('midtrans.driver');
    }

    public function test_firebase_config_enables_google_login_and_can_be_switched_off(): void
    {
        $this->get(route('login'))->assertDontSee('Lanjutkan dengan Google');

        $this->actingAs($this->superadmin)->put(route('admin.integrations.update', 'firebase'), ['firebase' => [
            'login_enabled' => '1', 'api_key' => 'AIzaTest', 'project_id' => 'mga-shop', 'auth_domain' => '', 'app_id' => '1:1:web:1',
        ]])->assertSessionHasNoErrors();

        $this->assertTrue(config('services.firebase.enabled'));
        $this->assertSame('mga-shop.firebaseapp.com', config('services.firebase.auth_domain'));
        auth()->logout();
        $this->get(route('login'))->assertSee('Lanjutkan dengan Google');

        $this->actingAs($this->superadmin)->put(route('admin.integrations.update', 'firebase'), ['firebase' => [
            'login_enabled' => '0', 'api_key' => 'AIzaTest', 'project_id' => 'mga-shop',
        ]])->assertSessionHasNoErrors();
        $this->assertFalse(config('services.firebase.enabled'));

        $this->put(route('admin.integrations.update', 'firebase'), ['firebase' => ['login_enabled' => '1', 'api_key' => 'AIzaTest', 'project_id' => 'Bad Project']])
            ->assertSessionHasErrors('firebase.project_id');
    }

    public function test_connection_tests_report_success_and_failure(): void
    {
        config([
            'services.midtrans.server_key' => 'SB-Mid-server-OK',
            'services.midtrans.client_key' => 'SB-Mid-client-OK',
            'services.rajaongkir.api_key' => 'bad-key',
            'services.firebase.api_key' => 'AIzaTest',
            'services.firebase.project_id' => 'mga-shop',
            'app.url' => 'https://mga.test',
        ]);

        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404),
            'rajaongkir.komerce.id/*' => Http::response(['meta' => ['message' => 'Invalid Api key, key not found', 'code' => 401]], 401),
            'identitytoolkit.googleapis.com/*' => Http::response(['projectId' => 'mga-shop', 'authorizedDomains' => ['localhost', 'mga-shop.firebaseapp.com']]),
        ]);

        $this->actingAs($this->superadmin)->post(route('admin.integrations.test', 'midtrans'))
            ->assertRedirect()
            ->assertSessionHas('integration_test.midtrans.ok', true);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/status') && $request->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-OK:')));

        $this->post(route('admin.integrations.test', 'rajaongkir'))
            ->assertSessionHas('integration_test.rajaongkir.ok', false);
        $this->assertStringContainsString('Invalid Api key', session('integration_test.rajaongkir.message'));

        $this->followingRedirects()->post(route('admin.integrations.test', 'firebase'))
            ->assertOk()
            ->assertSee('Koneksi berhasil')
            ->assertSee('mga.test belum ada di Authentication');

        $this->assertSame(3, ActivityLog::where('action', 'integrations.test')->count());
    }

    public function test_undecryptable_secret_falls_back_to_env(): void
    {
        config(['services.rajaongkir.api_key' => 'env-key']);
        $this->refreshSettingsDefaults();
        Setting::create(['key' => 'services.rajaongkir.api_key', 'value' => 'bukan-data-terenkripsi']);

        app(StoreSettings::class)->flush();
        app(StoreSettings::class)->apply();

        $this->assertSame('env-key', config('services.rajaongkir.api_key'));
    }

    /** StoreSettings mencatat nilai .env sekali; buat ulang supaya ikut config tes. */
    private function refreshSettingsDefaults(): void
    {
        $this->app->forgetInstance(StoreSettings::class);
    }
}
