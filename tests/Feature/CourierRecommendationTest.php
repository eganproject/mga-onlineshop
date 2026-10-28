<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourierRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://rajaongkir.test/api/v1';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
        $this->owner = User::factory()->admin()->create();
    }

    private function useRajaOngkir(): void
    {
        config([
            'shop.shipping.driver' => 'rajaongkir',
            'shop.shipping.origin_province_id' => '6',
            'shop.shipping.origin_district_id' => '1391',
            'shop.shipping.couriers' => ['jne'],
            'services.rajaongkir.api_key' => 'kunci-rahasia',
            'services.rajaongkir.base_url' => self::BASE,
        ]);
    }

    private function fakeApi(): void
    {
        Http::fake([
            self::BASE.'/destination/province' => Http::response(['data' => [
                ['id' => 6, 'name' => 'DKI JAKARTA'],
                ['id' => 11, 'name' => 'JAWA TIMUR'],
            ]]),
            self::BASE.'/destination/city/11' => Http::response(['data' => [
                ['id' => 443, 'name' => 'SIDOARJO'],
                ['id' => 444, 'name' => 'SURABAYA'],
            ]]),
            self::BASE.'/destination/district/444' => Http::response(['data' => [
                ['id' => 6100, 'name' => 'GUBENG', 'zip_code' => '60281'],
            ]]),
            self::BASE.'/calculate/district/domestic-cost' => function (Request $request) {
                $rows = [
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 22000, 'etd' => '2 day'],
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'YES', 'description' => 'Besok', 'cost' => 40000, 'etd' => '1 day'],
                    ['name' => 'J&T', 'code' => 'jnt', 'service' => 'EZ', 'description' => 'Reguler', 'cost' => 20000, 'etd' => '2-3 day'],
                ];

                // SiCepat hanya melayani dalam kota.
                if ($request['destination'] === '1391') {
                    $rows[] = ['name' => 'SiCepat', 'code' => 'sicepat', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 9000, 'etd' => '1 day'];
                }

                return Http::response(['data' => $rows]);
            },
        ]);
    }

    public function test_recommends_couriers_that_reach_every_sample_destination(): void
    {
        $this->useRajaOngkir();
        $this->fakeApi();

        $response = $this->actingAs($this->owner)->getJson(route('admin.store-settings.couriers'))->assertOk();

        $this->assertSame(['jne', 'jnt'], $response->json('recommended'));
        $this->assertSame(['sekitar gudang', 'Surabaya'], $response->json('destinations'));
        $this->assertSame(['available' => true, 'reach' => 2, 'services' => 2, 'cheapest' => 22000, 'etd' => '2 hari'], $response->json('couriers.jne'));
        $this->assertSame(1, $response->json('couriers.sicepat.reach'));
        $this->assertFalse($response->json('couriers.tiki.available'));

        // Semua kurir yang didukung ikut dicek dalam satu permintaan.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'domestic-cost')
            && $r['courier'] === implode(':', array_keys(StoreSettings::COURIERS)));

        // Hasil di-cache: tidak menembak API lagi.
        $count = count(Http::recorded());
        $this->getJson(route('admin.store-settings.couriers'))->assertOk();
        $this->assertCount($count, Http::recorded());

        $this->getJson(route('admin.store-settings.couriers', ['refresh' => 1]))->assertOk();
        $this->assertGreaterThan($count, count(Http::recorded()));
    }

    public function test_falls_back_to_one_courier_per_request_when_api_rejects_the_batch(): void
    {
        $this->useRajaOngkir();
        config(['shop.shipping.origin_province_id' => '11']); // sama dengan Surabaya → cuma sekitar gudang
        Http::fake([
            self::BASE.'/destination/province' => Http::response(['data' => [['id' => 11, 'name' => 'JAWA TIMUR']]]),
            self::BASE.'/calculate/district/domestic-cost' => function (Request $request) {
                if (str_contains($request['courier'], ':')) {
                    return Http::response(['meta' => ['message' => 'Invalid courier']], 422);
                }

                return $request['courier'] === 'pos'
                    ? Http::response(['data' => [['name' => 'POS', 'code' => 'pos', 'service' => 'Kilat', 'cost' => 15000, 'etd' => '3 day']]])
                    : Http::response(['meta' => ['message' => 'Not found']], 404);
            },
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('admin.store-settings.couriers'))->assertOk();

        $this->assertSame(['pos'], $response->json('recommended'));
        $this->assertSame(['sekitar gudang'], $response->json('destinations'));
    }

    public function test_requires_rajaongkir_and_handles_api_errors(): void
    {
        // Mode simulasi / tanpa API key.
        $this->actingAs($this->owner)->getJson(route('admin.store-settings.couriers'))
            ->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'Admin › Integrasi'));

        $this->useRajaOngkir();
        Http::fake(['*' => Http::response(['meta' => ['message' => 'Invalid key']], 401)]);

        $this->getJson(route('admin.store-settings.couriers'))
            ->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'tidak bisa dihubungi'));

        config(['shop.shipping.origin_district_id' => null]);
        $this->getJson(route('admin.store-settings.couriers'))->assertStatus(422);
    }

    public function test_only_settings_managers_can_check(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('admin.store-settings.couriers'))->assertForbidden();
    }

    public function test_unknown_saved_courier_codes_are_not_sent_to_rajaongkir(): void
    {
        config(['shop.shipping.couriers' => ['anteraja', 'jne', 'spx']]);
        $this->assertSame(['jne', 'spx'], StoreSettings::activeCouriers());

        config(['shop.shipping.couriers' => ['anteraja']]);
        $this->assertSame(['jne'], StoreSettings::activeCouriers());
    }

    public function test_settings_page_shows_full_courier_list_and_recommendation_button(): void
    {
        $this->actingAs($this->owner)->get(route('admin.store-settings.edit'))
            ->assertOk()->assertSee('SPX Express')->assertSee('Sentral Cargo')->assertDontSee('AnterAja')
            ->assertDontSee('Cek rekomendasi RajaOngkir</span>', false);

        $this->useRajaOngkir();
        Http::fake(['*' => Http::response(['data' => []])]);
        $this->get(route('admin.store-settings.edit'))->assertOk()->assertSee('Cek rekomendasi RajaOngkir');
    }
}
