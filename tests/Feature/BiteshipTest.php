<?php

namespace Tests\Feature;

use App\Contracts\ShipmentTracker;
use App\Contracts\ShippingProvider;
use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Exceptions\ShippingUnavailableException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\StoreSettings;
use App\Services\Shipping\BiteshipShipmentTracker;
use App\Services\Shipping\BiteshipShippingProvider;
use App\Services\Shipping\IndonesiaRegions;
use App\Services\Shipping\ShippingService;
use App\Support\ShippingConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Biteship sebagai pilihan penyedia ongkir selain RajaOngkir. */
class BiteshipTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://biteship.test';

    // Kode Kemendagri: Tebet (Jakarta Selatan) & Coblong (Kota Bandung).
    private const TEBET = '317401';

    private const COBLONG = '327302';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
    }

    private function useBiteship(array $overrides = []): void
    {
        config($overrides + [
            'shop.shipping.driver' => 'biteship',
            'shop.shipping.biteship.origin_province_id' => '31',
            'shop.shipping.biteship.origin_city_id' => '3174',
            'shop.shipping.biteship.origin_district_id' => self::TEBET,
            'shop.shipping.biteship.origin_postal_code' => null,
            'shop.shipping.biteship.couriers' => ['jne', 'sicepat'],
            'services.biteship.api_key' => 'biteship_test.kunci',
            'services.biteship.base_url' => self::BASE,
        ]);
    }

    private function fakeRates(): void
    {
        Http::fake([self::BASE.'/v1/rates/couriers' => Http::response([
            'success' => true,
            'object' => 'courier_pricing',
            'pricing' => [
                ['courier_name' => 'SiCepat', 'courier_code' => 'sicepat', 'courier_service_name' => 'Reguler', 'courier_service_code' => 'reg', 'description' => 'Layanan reguler',
                    'shipment_duration_range' => '2 - 3', 'shipment_duration_unit' => 'days', 'service_type' => 'standard', 'price' => 14000],
                ['courier_name' => 'JNE', 'courier_code' => 'jne', 'courier_service_name' => 'Reguler', 'courier_service_code' => 'reg', 'description' => 'Layanan reguler',
                    'shipment_duration_range' => '1 - 2', 'shipment_duration_unit' => 'days', 'service_type' => 'standard', 'price' => 11000],
                ['courier_name' => 'Gojek', 'courier_code' => 'gojek', 'courier_service_name' => 'Instant', 'courier_service_code' => 'instant',
                    'shipment_duration_range' => '1 - 3', 'shipment_duration_unit' => 'hours', 'service_type' => 'instant', 'price' => 30000],
            ],
        ])]);
    }

    public function test_bundled_regions_cover_indonesia_with_postal_codes(): void
    {
        $regions = new IndonesiaRegions;

        $this->assertCount(38, $regions->provinces());
        $this->assertContains('Kota Bandung', array_map(fn ($r) => $r->name, $regions->cities('32')));

        $tebet = collect($regions->districts('3174'))->firstWhere('id', self::TEBET);
        $this->assertSame('Tebet', $tebet->name);
        $this->assertMatchesRegularExpression('/^128\d\d$/', $tebet->postalCode);
        $this->assertSame($tebet->postalCode, $regions->postalCode(self::TEBET));

        // ID RajaOngkir/simulasi bukan kode Kemendagri.
        $this->assertNull($regions->postalCode('20101'));
        $this->assertNull($regions->postalCode('999999'));
        $this->assertSame([], $regions->cities('99'));
    }

    public function test_driver_binding_and_region_endpoints(): void
    {
        $this->useBiteship();

        $this->assertInstanceOf(BiteshipShippingProvider::class, app(ShippingProvider::class));
        $this->assertInstanceOf(BiteshipShipmentTracker::class, app(ShipmentTracker::class));

        $this->actingAs(User::factory()->create());
        $this->getJson(route('account.regions.provinces'))->assertOk()->assertJsonCount(38, 'data')->assertJsonFragment(['id' => '32', 'name' => 'Jawa Barat']);
        $this->getJson(route('account.regions.cities', '32'))->assertOk()->assertJsonFragment(['id' => '3273', 'name' => 'Kota Bandung']);
        $this->getJson(route('account.regions.districts', '3273'))->assertOk()->assertJsonFragment(['id' => self::COBLONG, 'name' => 'Coblong']);
    }

    public function test_rates_are_requested_by_postal_code_and_cached(): void
    {
        $this->useBiteship();
        $this->fakeRates();
        $regions = new IndonesiaRegions;

        $address = Address::factory()->create(['province_id' => '32', 'city_id' => '3273', 'district_id' => self::COBLONG, 'region_source' => 'biteship', 'postal_code' => '40135']);
        $rates = app(ShippingService::class)->rates($address, 1500);

        // Termurah dulu; kurir instan dibuang.
        $this->assertSame(['jne|REG', 'sicepat|REG'], array_map(fn ($r) => $r->key(), $rates));
        $this->assertSame(11000, $rates[0]->cost);
        $this->assertSame('1-2 hari', $rates[0]->etd);
        $this->assertSame('JNE', $rates[0]->courierName);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'biteship_test.kunci')
            && $r['origin_postal_code'] === (int) $regions->postalCode(self::TEBET)
            && $r['destination_postal_code'] === (int) $regions->postalCode(self::COBLONG)
            && $r['couriers'] === 'jne,sicepat'
            && $r['items'][0]['weight'] === 1500);

        app(ShippingService::class)->rates($address, 1500);
        Http::assertSentCount(1);
        $this->assertSame('jne', app(ShippingService::class)->findRate($address, 1500, 'jne|REG')->courier);
    }

    public function test_warehouse_postal_code_overrides_district_default(): void
    {
        $this->useBiteship(['shop.shipping.biteship.origin_postal_code' => '12870']);
        $this->fakeRates();

        $address = Address::factory()->create(['district_id' => self::COBLONG, 'region_source' => 'biteship']);
        app(ShippingService::class)->rates($address, 1000);

        Http::assertSent(fn (Request $r) => $r['origin_postal_code'] === 12870);
    }

    public function test_old_rajaongkir_address_falls_back_to_its_postal_code(): void
    {
        $this->useBiteship();
        $this->fakeRates();

        // Alamat lama: ID kecamatan RajaOngkir, region_source kosong.
        $old = Address::factory()->create(['district_id' => '1360', 'postal_code' => '40132']);
        $this->assertFalse($old->regionMatchesDriver());
        $this->assertNotEmpty(app(ShippingService::class)->rates($old, 1000));
        Http::assertSent(fn (Request $r) => $r['destination_postal_code'] === 40132);

        // Tanpa kode pos tidak bisa dihitung: minta alamat diperbarui.
        $noPostal = Address::factory()->create(['district_id' => '1360', 'postal_code' => null]);
        try {
            app(ShippingService::class)->rates($noPostal, 1000);
            $this->fail('Seharusnya gagal.');
        } catch (ShippingUnavailableException $e) {
            $this->assertStringContainsString('perlu diperbarui', $e->getMessage());
        }

        $this->actingAs($old->user)->get(route('account.addresses.edit', $old))->assertOk()->assertSee('Pilih ulang provinsi, kota, dan kecamatan');
    }

    public function test_biteship_address_asks_for_update_when_back_on_rajaongkir(): void
    {
        $address = Address::factory()->create(['district_id' => self::COBLONG, 'region_source' => 'biteship']);

        $this->expectException(ShippingUnavailableException::class);
        $this->expectExceptionMessage('perlu diperbarui');

        app(ShippingService::class)->rates($address, 1000);
    }

    public function test_saving_address_records_region_source(): void
    {
        $this->useBiteship();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('account.addresses.store'), [
            'label' => 'Rumah', 'recipient_name' => 'Budi', 'phone' => '081234567890',
            'province_id' => '32', 'city_id' => '3273', 'district_id' => self::COBLONG,
            'address_line' => 'Jl. Dago 1',
        ])->assertSessionHasNoErrors();

        $address = $customer->addresses()->sole();
        $this->assertSame('biteship', $address->region_source);
        $this->assertSame('Coblong', $address->district);
        $this->assertSame('Kota Bandung', $address->city);
        $this->assertSame((new IndonesiaRegions)->postalCode(self::COBLONG), $address->postal_code);
        $this->assertTrue($address->regionMatchesDriver());

        // ID RajaOngkir ditolak saat Biteship aktif.
        $this->post(route('account.addresses.store'), [
            'label' => 'Kantor', 'recipient_name' => 'Budi', 'phone' => '081234567890',
            'province_id' => '2', 'city_id' => '201', 'district_id' => '20101', 'address_line' => 'Jl. Dago 2',
        ])->assertSessionHasErrors('district_id');
    }

    public function test_api_failure_becomes_friendly_message(): void
    {
        $this->useBiteship();
        Http::fake([self::BASE.'/*' => Http::response(['success' => false, 'error' => 'Authorization failed', 'code' => 40101001], 401)]);

        $address = Address::factory()->create(['district_id' => self::COBLONG, 'region_source' => 'biteship']);

        $this->expectException(ShippingUnavailableException::class);
        $this->expectExceptionMessage('Ongkos kirim sedang tidak bisa dimuat');

        app(ShippingService::class)->rates($address, 1000);
    }

    public function test_checkout_and_manual_sale_work_with_biteship(): void
    {
        $this->useBiteship();
        $this->fakeRates();

        // Checkout pelanggan.
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create(['province_id' => '32', 'city_id' => '3273', 'district_id' => self::COBLONG, 'region_source' => 'biteship']);
        $product = Product::factory()->create(['price' => 100_000, 'stock' => 10, 'weight' => 600]);

        $this->actingAs($customer)->post(route('cart.store', $product));
        $this->get(route('checkout.index'))->assertOk()->assertSee('JNE');
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'])->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame('jne', $order->courier);
        $this->assertSame(11000, $order->shipping_cost);

        // Penjualan manual oleh CS.
        $this->actingAs(User::factory()->staff(StaffRole::CustomerService)->create());
        $this->get(route('admin.orders.create'))->assertOk()->assertSee('Tarif Biteship ke kecamatan tujuan');

        $rates = $this->getJson(route('admin.orders.manual.rates', ['district_id' => self::COBLONG, 'weight' => 1200]))->assertOk()->json('data');
        $this->assertSame('jne|REG', $rates[0]['key']);

        $this->post(route('admin.orders.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'customer_name' => 'Budi Pembeli', 'customer_phone' => '081234567890',
            'shipping_mode' => 'courier',
            'province_id' => '32', 'city_id' => '3273', 'district_id' => self::COBLONG,
            'address_line' => 'Jl. Dago 1', 'rate' => 'jne|REG',
        ])->assertSessionHasNoErrors();

        $manual = Order::latest('id')->first();
        $this->assertSame(11000, $manual->shipping_cost);
        $this->assertSame('Jl. Dago 1, Coblong, Kota Bandung, Jawa Barat', $manual->shipping_address);
    }

    public function test_superadmin_switches_provider_and_key_is_encrypted(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.integrations.update', 'rajaongkir'), ['rajaongkir' => ['driver' => 'biteship', 'biteship_api_key' => '']])
            ->assertSessionHasErrors('rajaongkir.biteship_api_key');
        $this->assertSame('fake', config('shop.shipping.driver'));

        $this->put(route('admin.integrations.update', 'rajaongkir'), ['rajaongkir' => ['driver' => 'biteship', 'biteship_api_key' => 'biteship_live.rahasia123']])
            ->assertSessionHasNoErrors();

        $this->assertSame('biteship', config('shop.shipping.driver'));
        $this->assertSame('biteship_live.rahasia123', config('services.biteship.api_key'));
        $this->assertStringNotContainsString('rahasia123', Setting::find('services.biteship.api_key')->value);
        $this->assertInstanceOf(BiteshipShippingProvider::class, app(ShippingProvider::class));

        $this->get(route('admin.integrations.index'))->assertOk()
            ->assertSee('API key Biteship')
            ->assertDontSee('biteship_live.rahasia123');

        // Kembali ke RajaOngkir tanpa kuncinya ditolak; kunci Biteship tetap tersimpan.
        $this->put(route('admin.integrations.update', 'rajaongkir'), ['rajaongkir' => ['driver' => 'rajaongkir']])
            ->assertSessionHasErrors('rajaongkir.api_key');
        $this->put(route('admin.integrations.update', 'rajaongkir'), ['rajaongkir' => ['driver' => 'fake']])->assertSessionHasNoErrors();
        $this->assertSame('fake', config('shop.shipping.driver'));
        $this->assertSame('biteship_live.rahasia123', config('services.biteship.api_key'));
    }

    public function test_connection_test_uses_selected_provider(): void
    {
        $this->useBiteship();
        $this->actingAs(User::factory()->admin()->create());

        Http::fake([self::BASE.'/v1/maps/areas*' => Http::response(['success' => true, 'areas' => [['id' => 'IDNP6IDNC148IDND836IDZ12810']]])]);
        $this->post(route('admin.integrations.test', 'rajaongkir'))->assertSessionHas('integration_test.rajaongkir.ok', true);
        $this->assertStringContainsString('biteship_test', implode(' ', session('integration_test.rajaongkir.notes')));

        Http::swap(new Factory);
        Http::fake([self::BASE.'/*' => Http::response(['success' => false, 'error' => 'Authorization failed'], 401)]);
        $this->post(route('admin.integrations.test', 'rajaongkir'))->assertSessionHas('integration_test.rajaongkir.ok', false);
        $this->assertStringContainsString('Authorization failed', session('integration_test.rajaongkir.message'));
    }

    public function test_store_settings_keep_origin_and_couriers_per_provider(): void
    {
        $owner = User::factory()->admin()->create();
        $payload = fn (array $overrides) => $overrides + [
            'name' => 'MGA', 'full_name' => 'Media Gudang Acc', 'whatsapp_button' => '1',
            'province_id' => '', 'city_id' => '', 'district_id' => '',
            'tracking_sync' => '0', 'payment_expiry_hours' => 12, 'auto_complete_days' => 5,
        ];

        // Simulasi/RajaOngkir.
        $this->actingAs($owner)->put(route('admin.store-settings.update'), $payload([
            'province_id' => '2', 'city_id' => '201', 'district_id' => '20101', 'couriers' => ['jne', 'spx'],
        ]))->assertSessionHasNoErrors();

        // Pindah ke Biteship: asal & kurir sendiri, kode kurir RajaOngkir ditolak.
        // (disimpan lewat StoreSettings, seperti dari Admin › Integrasi, supaya bertahan saat pengaturan dimuat ulang)
        app(StoreSettings::class)->save(['shop.shipping.driver' => 'biteship', 'services.biteship.api_key' => 'biteship_test.kunci']);
        $this->get(route('admin.store-settings.edit'))->assertOk()
            ->assertSee('AnterAja')->assertDontSee('SPX Express')
            ->assertSee('Kecamatan asal untuk Biteship belum dipilih')
            ->assertDontSee('Cek rekomendasi RajaOngkir');

        $this->put(route('admin.store-settings.update'), $payload(['couriers' => ['spx']]))->assertSessionHasErrors('couriers.0');
        $this->put(route('admin.store-settings.update'), $payload([
            'province_id' => '31', 'city_id' => '3174', 'district_id' => self::TEBET, 'origin_postal_code' => '12870',
            'couriers' => ['jne', 'anteraja'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(self::TEBET, ShippingConfig::origin('district_id'));
        $this->assertSame('Tebet, Kota Jakarta Selatan, DKI Jakarta', ShippingConfig::origin('label'));
        $this->assertSame('12870', ShippingConfig::origin('postal_code'));
        $this->assertSame(['jne', 'anteraja'], ShippingConfig::couriers());

        // Pengaturan RajaOngkir tidak tersentuh.
        $this->assertSame('20101', config('shop.shipping.origin_district_id'));
        $this->assertSame(['jne', 'spx'], config('shop.shipping.couriers'));

        app(StoreSettings::class)->save(['shop.shipping.driver' => 'fake']);
        $this->assertSame('20101', ShippingConfig::origin('district_id'));
        $this->assertSame(['jne', 'spx'], ShippingConfig::couriers());
    }

    public function test_tracker_parses_public_tracking_response(): void
    {
        Http::fake([self::BASE.'/v1/trackings/*' => Http::response([
            'success' => true,
            'object' => 'tracking',
            'waybill_id' => 'IDE123',
            'courier' => ['company' => 'idexpress'],
            'status' => 'delivered',
            'history' => [
                ['note' => 'Paket dijemput kurir', 'updated_at' => '2026-11-10T09:00:00+07:00', 'status' => 'picked'],
                ['note' => '', 'updated_at' => '2026-11-11T08:00:00+07:00', 'status' => 'dropping_off'],
                ['note' => 'Paket diterima oleh BUDI', 'updated_at' => '2026-11-12T14:05:00+07:00', 'status' => 'delivered'],
            ],
        ])]);

        // Pesanan lama memakai kode kurir RajaOngkir ("ide") → kode Biteship ("idexpress").
        $order = Order::factory()->status(OrderStatus::Shipped)->create(['courier' => 'ide', 'tracking_number' => 'IDE123']);
        $result = (new BiteshipShipmentTracker('kunci', self::BASE))->track($order);

        $this->assertTrue($result->delivered);
        $this->assertCount(3, $result->events);
        $this->assertSame('Paket diterima oleh BUDI', $result->latest()->description);
        $this->assertSame('Paket dalam perjalanan ke tujuan', $result->events[1]->description);
        $this->assertSame('2026-11-12 14:05', $result->deliveredAt->format('Y-m-d H:i'));

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->hasHeader('Authorization', 'kunci')
            && str_ends_with($r->url(), '/v1/trackings/IDE123/couriers/idexpress'));
    }

    // ---------------------------------------------------------------
    // Kurir instan (Lalamove, Gojek, Grab): butuh koordinat gudang + pin alamat
    // ---------------------------------------------------------------

    private function useInstant(): void
    {
        $this->useBiteship([
            'shop.shipping.biteship.origin_latitude' => '-6.2260000',
            'shop.shipping.biteship.origin_longitude' => '106.8530000',
            'shop.shipping.biteship.instant_couriers' => ['lalamove', 'gojek'],
            'shop.shipping.biteship.instant_max_km' => 40,
        ]);

        Http::fake([self::BASE.'/v1/rates/couriers' => fn (Request $r) => isset($r['destination_latitude'])
            ? Http::response(['success' => true, 'pricing' => [
                ['courier_name' => 'Lalamove', 'courier_code' => 'lalamove', 'courier_service_name' => 'Motorcycle', 'courier_service_code' => 'motorcycle',
                    'shipment_duration_range' => '1 - 3', 'shipment_duration_unit' => 'hours', 'service_type' => 'instant', 'price' => 25000],
            ]])
            : Http::response(['success' => true, 'pricing' => [
                ['courier_name' => 'JNE', 'courier_code' => 'jne', 'courier_service_name' => 'Reguler', 'courier_service_code' => 'reg',
                    'shipment_duration_range' => '1 - 2', 'shipment_duration_unit' => 'days', 'service_type' => 'standard', 'price' => 11000],
            ]]),
        ]);
    }

    public function test_instant_couriers_are_offered_for_pinned_address_in_range(): void
    {
        $this->useInstant();

        $pinned = Address::factory()->create(['district_id' => self::TEBET, 'region_source' => 'biteship', 'latitude' => -6.2400000, 'longitude' => 106.8600000]);
        $rates = app(ShippingService::class)->rates($pinned, 1000);

        $this->assertSame(['jne|REG', 'lalamove|MOTORCYCLE'], array_map(fn ($r) => $r->key(), $rates));
        $this->assertSame('1-3 jam', $rates[1]->etd);
        $this->assertSame(0, $rates[1]->minDays());

        Http::assertSent(fn (Request $r) => ($r['origin_latitude'] ?? null) === -6.226
            && $r['destination_longitude'] === 106.86
            && $r['couriers'] === 'lalamove,gojek'
            && ! isset($r['destination_postal_code']));

        // Tanpa pin: hanya kurir reguler, tanpa permintaan kedua.
        Http::swap(new Factory);
        $this->useInstant();
        $plain = Address::factory()->create(['district_id' => self::TEBET, 'region_source' => 'biteship']);
        $this->assertSame(['jne|REG'], array_map(fn ($r) => $r->key(), app(ShippingService::class)->rates($plain, 1000)));
        Http::assertSentCount(1);
    }

    public function test_instant_couriers_skip_far_address_and_never_break_regular_rates(): void
    {
        $this->useInstant();

        // Bandung: jauh di luar jangkauan 40 km dari gudang di Jakarta.
        $far = Address::factory()->create(['district_id' => self::COBLONG, 'region_source' => 'biteship', 'latitude' => -6.8900000, 'longitude' => 107.6100000]);
        $this->assertSame(['jne|REG'], array_map(fn ($r) => $r->key(), app(ShippingService::class)->rates($far, 1000)));
        Http::assertSentCount(1);

        // API kurir instan gagal: tarif reguler tetap tampil.
        Http::swap(new Factory);
        $this->useInstant();
        Http::swap(new Factory);
        Http::fake([self::BASE.'/v1/rates/couriers' => fn (Request $r) => isset($r['destination_latitude'])
            ? Http::response(['success' => false, 'error' => 'No courier available'], 400)
            : Http::response(['success' => true, 'pricing' => [
                ['courier_name' => 'JNE', 'courier_code' => 'jne', 'courier_service_name' => 'Reguler', 'courier_service_code' => 'reg', 'price' => 11000],
            ]]),
        ]);
        $near = Address::factory()->create(['district_id' => self::TEBET, 'region_source' => 'biteship', 'latitude' => -6.2300000, 'longitude' => 106.8500000]);
        $this->assertSame(['jne|REG'], array_map(fn ($r) => $r->key(), app(ShippingService::class)->rates($near, 1000)));
    }

    public function test_address_form_saves_pin_and_shows_map_only_when_instant_is_ready(): void
    {
        $customer = User::factory()->create();
        $this->useBiteship();
        $this->actingAs($customer)->get(route('account.addresses.create'))->assertOk()->assertDontSee('Pin lokasi');

        $this->useInstant();
        $this->get(route('account.addresses.create'))->assertOk()->assertSee('Pin lokasi')->assertSee('x-data="mapPin(', false);

        $payload = [
            'label' => 'Rumah', 'recipient_name' => 'Budi', 'phone' => '081234567890',
            'province_id' => '31', 'city_id' => '3174', 'district_id' => self::TEBET, 'address_line' => 'Jl. Tebet 1',
        ];
        $this->post(route('account.addresses.store'), $payload + ['latitude' => '-6.2412345678', 'longitude' => '106.8612'])->assertSessionHasNoErrors();

        $address = $customer->addresses()->sole();
        $this->assertSame([-6.2412346, 106.8612], $address->coordinates());

        $this->post(route('account.addresses.store'), $payload + ['latitude' => '-6.24', 'longitude' => ''])->assertSessionHasErrors('longitude');
        $this->post(route('account.addresses.store'), $payload + ['latitude' => '123', 'longitude' => '106'])->assertSessionHasErrors('latitude');

        // Pin dihapus saat alamat disimpan tanpa koordinat.
        $this->put(route('account.addresses.update', $address), $payload + ['latitude' => '', 'longitude' => ''])->assertSessionHasNoErrors();
        $this->assertNull($address->fresh()->coordinates());
    }

    public function test_store_settings_save_instant_couriers_with_warehouse_pin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app(StoreSettings::class)->save(['shop.shipping.driver' => 'biteship', 'services.biteship.api_key' => 'biteship_test.kunci']);

        $payload = fn (array $overrides) => $overrides + [
            'name' => 'MGA', 'full_name' => 'Media Gudang Acc', 'whatsapp_button' => '1',
            'province_id' => '', 'city_id' => '', 'district_id' => '', 'couriers' => ['jne'],
            'tracking_sync' => '0', 'payment_expiry_hours' => 12, 'auto_complete_days' => 5, 'instant_submitted' => '1',
        ];

        $this->get(route('admin.store-settings.edit'))->assertOk()->assertSee('Lalamove')->assertSee('Pin lokasi gudang');

        // Kurir instan tanpa pin gudang ditolak.
        $this->put(route('admin.store-settings.update'), $payload(['instant_couriers' => ['lalamove']]))->assertSessionHasErrors('origin_latitude');
        $this->put(route('admin.store-settings.update'), $payload(['instant_couriers' => ['jne']]))->assertSessionHasErrors('instant_couriers.0');

        $this->put(route('admin.store-settings.update'), $payload([
            'instant_couriers' => ['lalamove', 'grab'], 'origin_latitude' => '-6.226', 'origin_longitude' => '106.853',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['lalamove', 'grab'], ShippingConfig::instantCouriers());
        $this->assertSame([-6.226, 106.853], ShippingConfig::originCoordinates());
        $this->assertTrue(ShippingConfig::instantEnabled());

        // Semua centang dilepas = kurir instan mati, kurir reguler tidak tersentuh.
        $this->put(route('admin.store-settings.update'), $payload(['origin_latitude' => '-6.226', 'origin_longitude' => '106.853']))->assertSessionHasNoErrors();
        $this->assertFalse(ShippingConfig::instantEnabled());
        $this->assertSame(['jne'], ShippingConfig::couriers());

        // Di luar Biteship kurir instan tidak pernah aktif.
        app(StoreSettings::class)->save(['shop.shipping.driver' => 'fake']);
        $this->assertSame([], ShippingConfig::instantCouriers());
        $this->get(route('admin.store-settings.edit'))->assertOk()->assertDontSee('Pin lokasi gudang');
    }
}
