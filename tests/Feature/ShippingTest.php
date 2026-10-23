<?php

namespace Tests\Feature;

use App\Contracts\ShippingProvider;
use App\Exceptions\ShippingUnavailableException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\FakeShippingProvider;
use App\Services\Shipping\RajaOngkirShippingProvider;
use App\Services\Shipping\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://rajaongkir.test/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        // Samakan dengan cache produksi (database/redis): nilai diserialisasi
        // dan objek ditolak saat dibaca kembali.
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
    }

    // ---------------------------------------------------------------
    // Data wilayah & buku alamat
    // ---------------------------------------------------------------

    public function test_region_endpoints_return_cascading_data(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('account.regions.provinces'))
            ->assertOk()
            ->assertJsonFragment(['id' => '2', 'name' => 'Jawa Barat']);

        $this->getJson(route('account.regions.cities', '2'))
            ->assertOk()
            ->assertJsonFragment(['id' => '201', 'name' => 'Kota Bandung']);

        $this->getJson(route('account.regions.districts', '201'))
            ->assertOk()
            ->assertJsonFragment(['id' => '20101', 'name' => 'Coblong', 'postal_code' => '40132']);

        $this->getJson(route('account.regions.cities', '999'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('account.regions.districts', 'abc'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_region_endpoints_require_customer_login(): void
    {
        $this->getJson(route('account.regions.provinces'))->assertUnauthorized();
    }

    public function test_address_names_come_from_provider_not_from_form(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('account.addresses.store'), [
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province_id' => '6',
            'city_id' => '601',
            'district_id' => '60101',
            'province' => 'Diabaikan',
            'address_line' => 'Jl. Raya Gubeng 1',
        ])->assertRedirect(route('account.addresses.index'));

        $address = $customer->addresses()->first();
        $this->assertSame('Jawa Timur', $address->province);
        $this->assertSame('Kota Surabaya', $address->city);
        $this->assertSame('Gubeng', $address->district);
        $this->assertSame('60281', $address->postal_code); // terisi otomatis dari data kecamatan
        $this->assertTrue($address->canShip());
    }

    public function test_address_with_mismatched_regions_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('account.addresses.store'), [
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province_id' => '1',     // DKI Jakarta
            'city_id' => '201',       // Kota Bandung (bukan di DKI)
            'district_id' => '20101',
            'address_line' => 'Jl. Dago 1',
        ])->assertSessionHasErrors('district_id');

        $this->assertSame(0, Address::count());
    }

    public function test_address_shows_error_when_region_provider_is_down(): void
    {
        $this->useRajaOngkir();
        Http::fake(['*' => Http::response(['meta' => ['code' => 500]], 500)]);

        $this->actingAs(User::factory()->create())->post(route('account.addresses.store'), [
            'label' => 'Rumah', 'recipient_name' => 'Budi', 'phone' => '081234567890',
            'province_id' => '1', 'city_id' => '101', 'district_id' => '10101', 'address_line' => 'Jl. A',
        ])->assertSessionHasErrors(['district_id' => 'Data provinsi sedang tidak bisa dimuat. Coba beberapa saat lagi.']);

        $this->getJson(route('account.regions.provinces'))->assertStatus(503)->assertJsonPath('data', []);
    }

    public function test_editing_from_checkout_returns_to_checkout(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->withoutRegion()->create();

        $this->actingAs($customer)->get(route('account.addresses.edit', $address))->assertOk()->assertSee('format lama');

        $this->put(route('account.addresses.update', $address), [
            'label' => 'Rumah', 'recipient_name' => 'Budi', 'phone' => '081234567890',
            'province_id' => '2', 'city_id' => '201', 'district_id' => '20102', 'address_line' => 'Jl. A',
            'redirect_to' => 'checkout',
        ])->assertRedirect(route('checkout.index', ['alamat' => $address->id]));

        $this->assertSame('Sukajadi', $address->refresh()->district);
    }

    // ---------------------------------------------------------------
    // Ongkir di checkout
    // ---------------------------------------------------------------

    public function test_checkout_rates_endpoint_returns_sorted_rates_for_selected_address(): void
    {
        $customer = User::factory()->create();
        $bandung = Address::factory()->for($customer)->create();
        $medan = Address::factory()->for($customer)->create([
            'province_id' => '8', 'city_id' => '801', 'district_id' => '80101', 'is_default' => false,
        ]);
        $product = Product::factory()->create(['weight' => 1500, 'stock' => 10]);

        $this->actingAs($customer)->post(route('cart.store', $product));

        $near = $this->getJson(route('checkout.rates', ['address_id' => $bandung->id]))->assertOk()->json('rates');
        $far = $this->getJson(route('checkout.rates', ['address_id' => $medan->id]))->assertOk()->json('rates');

        $costs = array_column($near, 'cost');
        $sorted = $costs;
        sort($sorted);
        $this->assertSame($sorted, $costs, 'Tarif harus urut dari termurah.');

        // 1,5 kg dibulatkan jadi 2 kg; JNE REG satu pulau = 11.000/kg.
        $this->assertSame(22000, collect($near)->firstWhere('key', 'jne|REG')['cost']);
        $this->assertGreaterThan(collect($near)->firstWhere('key', 'jne|REG')['cost'], collect($far)->firstWhere('key', 'jne|REG')['cost']);
        // Layanan "besok sampai" tidak tersedia ke luar pulau.
        $this->assertNull(collect($far)->firstWhere('key', 'jne|YES'));
        $this->assertSame('Rp22.000', collect($near)->firstWhere('key', 'jne|REG')['cost_label']);
    }

    public function test_checkout_rates_endpoint_rejects_other_customers_address(): void
    {
        $customer = User::factory()->create();
        $otherAddress = Address::factory()->create();
        $this->actingAs($customer)->post(route('cart.store', Product::factory()->create()));

        $this->getJson(route('checkout.rates', ['address_id' => $otherAddress->id]))->assertNotFound();
    }

    public function test_address_without_district_cannot_get_rates_or_checkout(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->withoutRegion()->create();
        $this->actingAs($customer)->post(route('cart.store', Product::factory()->create(['stock' => 3])));

        $this->get(route('checkout.index'))->assertOk()->assertSee('Lengkapi alamat');
        $this->getJson(route('checkout.rates', ['address_id' => $address->id]))
            ->assertOk()
            ->assertJsonPath('rates', [])
            ->assertJsonPath('error', 'Alamat ini belum lengkap. Edit alamat dan pilih provinsi, kota, dan kecamatan.');

        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'])
            ->assertSessionHasErrors('shipping');
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_uses_cached_rate_shown_on_checkout_page(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $this->actingAs($customer)->post(route('cart.store', Product::factory()->create(['stock' => 3, 'weight' => 200])));

        $this->get(route('checkout.index'))->assertOk()->assertSee('JNE'); // ongkir masuk cache
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'])
            ->assertRedirect(route('account.orders.payment', Order::firstOrFail()));

        $this->assertSame(11000, Order::firstOrFail()->shipping_cost);
    }

    public function test_unknown_shipping_service_is_rejected(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $this->actingAs($customer)->post(route('cart.store', Product::factory()->create(['stock' => 3])));

        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'kurir|PALSU'])
            ->assertSessionHasErrors(['shipping' => 'Layanan pengiriman tidak tersedia. Silakan pilih ulang.']);
    }

    public function test_order_stores_district_as_destination(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();
        $this->actingAs($customer)->post(route('cart.store', Product::factory()->create(['stock' => 3])));

        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'sicepat|REG'])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('20101', $order->destination_id);
        $this->assertSame('sicepat', $order->courier);
        $this->assertStringContainsString('Coblong', $order->shipping_address);
    }

    // ---------------------------------------------------------------
    // Driver RajaOngkir (Komerce)
    // ---------------------------------------------------------------

    public function test_rajaongkir_regions_are_mapped_and_cached(): void
    {
        $this->useRajaOngkir();
        Http::fake([
            self::BASE.'/destination/province' => Http::response(['meta' => ['code' => 200], 'data' => [
                ['id' => 10, 'name' => 'JAWA TENGAH'],
                ['id' => 6, 'name' => 'DKI JAKARTA'],
            ]]),
            self::BASE.'/destination/city/6' => Http::response(['data' => [
                ['id' => 151, 'name' => 'JAKARTA SELATAN', 'zip_code' => '0'],
            ]]),
            self::BASE.'/destination/district/151' => Http::response(['data' => [
                ['id' => 1360, 'name' => 'KEBAYORAN BARU', 'zip_code' => '12120'],
            ]]),
        ]);

        $shipping = app(ShippingService::class);
        $provinces = $shipping->provinces();
        $this->assertSame(['DKI Jakarta', 'Jawa Tengah'], array_column($provinces, 'name'));
        $this->assertSame('6', $provinces[0]->id);

        $city = $shipping->cities('6')[0];
        $this->assertSame('Jakarta Selatan', $city->name);
        $this->assertNull($city->postalCode);
        $this->assertSame('12120', $shipping->districts('151')[0]->postalCode);

        // Panggilan kedua dilayani dari cache.
        $shipping->provinces();
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->hasHeader('key', 'kunci-rahasia'));
    }

    public function test_rajaongkir_rates_use_district_endpoint_and_are_cached(): void
    {
        $this->useRajaOngkir();
        Http::fake([
            self::BASE.'/calculate/district/domestic-cost' => Http::response(['data' => [
                ['name' => 'Jalur Nugraha Ekakurir (JNE)', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 24000, 'etd' => '2 day'],
                ['name' => 'SiCepat Express', 'code' => 'sicepat', 'service' => 'GOKIL', 'description' => 'Cargo', 'cost' => 18000, 'etd' => '3-5 day'],
                ['name' => 'rusak'],
            ]]),
        ]);

        $address = Address::factory()->create(['district_id' => '1376']);
        $shipping = app(ShippingService::class);
        $rates = $shipping->rates($address, 1200);

        $this->assertCount(2, $rates);
        $this->assertSame('sicepat|GOKIL', $rates[0]->key()); // termurah dulu
        $this->assertSame('2 hari', $rates[1]->etd);
        $this->assertSame(2, $rates[1]->minDays());

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r['origin'] === '1391'
            && $r['destination'] === '1376'
            && (int) $r['weight'] === 1200
            && $r['courier'] === 'jne:sicepat');

        $shipping->rates($address, 1200);
        Http::assertSentCount(1);
    }

    public function test_rajaongkir_failure_becomes_friendly_message(): void
    {
        $this->useRajaOngkir();
        Http::fake(['*' => Http::response(['meta' => ['message' => 'Invalid key']], 401)]);

        $this->expectException(ShippingUnavailableException::class);
        $this->expectExceptionMessage('Ongkos kirim sedang tidak bisa dimuat. Coba beberapa saat lagi.');

        app(ShippingService::class)->rates(Address::factory()->create(), 1000);
    }

    public function test_rajaongkir_requires_origin_district(): void
    {
        $this->useRajaOngkir(origin: null);
        Http::fake();

        $this->expectException(ShippingUnavailableException::class);
        app(ShippingService::class)->rates(Address::factory()->create(), 1000);
    }

    public function test_region_command_lists_districts(): void
    {
        $this->artisan('ongkir:wilayah', ['--kota' => '101'])
            ->expectsOutputToContain('Kebayoran Baru')
            ->expectsOutputToContain('SHOP_ORIGIN_DISTRICT_ID')
            ->assertSuccessful();

        $this->artisan('ongkir:wilayah', ['--cari' => 'bali'])->expectsOutputToContain('Bali')->assertSuccessful();
    }

    public function test_fake_provider_is_the_default_driver(): void
    {
        $this->assertInstanceOf(FakeShippingProvider::class, app(ShippingProvider::class));
    }

    private function useRajaOngkir(?string $origin = '1391'): void
    {
        config([
            'shop.shipping.driver' => 'rajaongkir',
            'shop.shipping.origin_district_id' => $origin,
            'shop.shipping.couriers' => ['jne', 'sicepat'],
            'services.rajaongkir.api_key' => 'kunci-rahasia',
            'services.rajaongkir.base_url' => self::BASE,
        ]);

        $this->assertInstanceOf(RajaOngkirShippingProvider::class, app(ShippingProvider::class));
    }
}
