<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Address;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\StoreSettings;
use App\Services\Shipping\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'MGA',
            'full_name' => 'Media Gudang Acc',
            'tagline' => 'Gudangnya aksesoris',
            'email' => 'halo@mga.test',
            'whatsapp' => '0812-3456-7890',
            'whatsapp_button' => '1',
            'whatsapp_message' => 'Halo MGA, saya mau tanya.',
            'address' => 'Jl. Dago No. 1, Bandung',
            'instagram' => 'https://instagram.com/mga.official/',
            'tiktok' => '@mga.tiktok',
            'facebook' => 'https://facebook.com/mga',
            'province_id' => '',
            'city_id' => '',
            'district_id' => '',
            'couriers' => ['jne', 'sicepat'],
            'tracking_sync' => '1',
            'payment_expiry_hours' => 12,
            'auto_complete_days' => 5,
        ], $overrides);
    }

    public function test_settings_page_renders(): void
    {
        $this->actingAs($this->owner)->get(route('admin.store-settings.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Toko')
            ->assertSee('Kurir yang ditawarkan di checkout');
    }

    public function test_saving_settings_overrides_config_and_is_normalized(): void
    {
        $this->actingAs($this->owner)->put(route('admin.store-settings.update'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('6281234567890', config('shop.whatsapp'));
        $this->assertSame('mga.official', config('shop.instagram'));
        $this->assertSame('mga.tiktok', config('shop.tiktok'));
        $this->assertSame(['jne', 'sicepat'], config('shop.shipping.couriers'));
        $this->assertTrue(config('shop.shipping.tracking_sync'));
        $this->assertSame(12, config('shop.payment.expiry_hours'));
        $this->assertSame(5, config('shop.orders.auto_complete_days'));

        // Tersimpan di database dan dipakai lagi saat aplikasi boot berikutnya.
        $this->assertSame('6281234567890', Setting::find('shop.whatsapp')->value);
        config(['shop.whatsapp' => null, 'shop.payment.expiry_hours' => 24]);
        app(StoreSettings::class)->apply();
        $this->assertSame('6281234567890', config('shop.whatsapp'));
        $this->assertSame(12, config('shop.payment.expiry_hours'));

        // Nilai yang sama dengan .env tidak disimpan (tetap ikut .env).
        $this->assertNull(Setting::find('shop.name'));

        $log = ActivityLog::where('action', 'settings.update')->sole();
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame('6281234567890', $log->properties['perubahan']['WhatsApp']['menjadi']);

        // Halaman toko memakai nilai baru.
        $this->get(route('home'))->assertSee('wa.me/6281234567890', false);
    }

    public function test_couriers_setting_filters_checkout_rates(): void
    {
        $this->actingAs($this->owner)->put(route('admin.store-settings.update'), $this->payload(['couriers' => ['jne']]));

        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $rates = app(ShippingService::class)->rates($address, 1000);

        $this->assertNotEmpty($rates);
        $this->assertSame(['jne'], array_values(array_unique(array_map(fn ($r) => $r->courier, $rates))));
    }

    public function test_origin_district_is_validated_and_label_resolved(): void
    {
        $this->actingAs($this->owner)->put(route('admin.store-settings.update'), $this->payload([
            'province_id' => '2', 'city_id' => '201', 'district_id' => '20101',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('20101', config('shop.shipping.origin_district_id'));
        $this->assertSame('Coblong, Kota Bandung, Jawa Barat', config('shop.shipping.origin_label'));

        $this->put(route('admin.store-settings.update'), $this->payload([
            'province_id' => '2', 'city_id' => '201', 'district_id' => '99999',
        ]))->assertSessionHasErrors('district_id');
        $this->assertSame('20101', config('shop.shipping.origin_district_id'));

        $this->get(route('admin.regions.districts', '201'))->assertOk()->assertJsonFragment(['name' => 'Coblong']);
    }

    public function test_payment_expiry_setting_applies_to_new_orders(): void
    {
        $this->actingAs($this->owner)->put(route('admin.store-settings.update'), $this->payload(['payment_expiry_hours' => 6]));

        $order = Order::factory()->unpaid()->create(['payment_due_at' => null]);

        $this->assertEqualsWithDelta(6 * 3600, $order->created_at->diffInSeconds($order->paymentDeadline()), 5);
    }

    public function test_clearing_a_field_falls_back_to_env_value(): void
    {
        config(['shop.address' => 'Alamat dari env']);
        // Simulasikan nilai bawaan .env saat aplikasi boot.
        $this->app->forgetInstance(StoreSettings::class);
        app(StoreSettings::class)->apply();

        $this->actingAs($this->owner)->put(route('admin.store-settings.update'), $this->payload(['address' => 'Alamat baru']));
        $this->assertSame('Alamat baru', config('shop.address'));

        $this->put(route('admin.store-settings.update'), $this->payload(['address' => '']));
        $this->assertSame('Alamat dari env', config('shop.address'));
        $this->assertNull(Setting::find('shop.address'));
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->owner)->put(route('admin.store-settings.update'), $this->payload([
            'whatsapp' => '12', 'couriers' => [], 'payment_expiry_hours' => 0, 'instagram' => 'ada spasi',
        ]))->assertSessionHasErrors(['whatsapp', 'couriers', 'payment_expiry_hours', 'instagram']);

        $this->put(route('admin.store-settings.update'), $this->payload(['couriers' => ['jne', 'kurirpalsu']]))
            ->assertSessionHasErrors('couriers.1');

        $this->assertSame(0, Setting::count());
    }
}
