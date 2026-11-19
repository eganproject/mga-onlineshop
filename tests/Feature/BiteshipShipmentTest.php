<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Pesan kurir lewat Biteship dari detail pesanan (POST /v1/orders) dan cetak label resi. */
class BiteshipShipmentTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://biteship.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shop.shipping.driver' => 'biteship',
            'shop.shipping.biteship.origin_district_id' => '317401',
            'shop.shipping.biteship.origin_postal_code' => '12810',
            'shop.shipping.biteship.origin_latitude' => -6.2264,
            'shop.shipping.biteship.origin_longitude' => 106.8526,
            'shop.shipping.biteship.instant_couriers' => ['gojek'],
            'shop.address' => 'Jl. Tebet Raya No. 1, Jakarta Selatan',
            'shop.whatsapp' => '6281234567890',
            'services.biteship.api_key' => 'biteship_test.kunci',
            'services.biteship.base_url' => self::BASE,
        ]);

        $this->actingAs(User::factory()->staff(StaffRole::Admin)->create());
    }

    private function order(array $attributes = []): Order
    {
        return Order::factory()->create($attributes + [
            'destination_postal_code' => '40132',
            'shipping_address' => 'Jl. Dago 10, Coblong, Kota Bandung, Jawa Barat 40132',
        ]);
    }

    private function biteshipOrder(array $overrides = []): array
    {
        return array_replace_recursive([
            'success' => true,
            'id' => 'ord_5f1a',
            'courier' => ['tracking_id' => 'trk_1', 'waybill_id' => 'JNE0012345678', 'company' => 'jne', 'type' => 'reg', 'link' => 'https://track.biteship.com/trk_1'],
            'price' => 18000,
            'status' => 'confirmed',
        ], $overrides);
    }

    public function test_creating_a_shipment_books_the_courier_and_marks_the_order_shipped(): void
    {
        Http::fake([self::BASE.'/v1/orders' => Http::response($this->biteshipOrder(), 200)]);
        $order = $this->order();

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Buat pengiriman Biteship');

        $this->post(route('admin.orders.shipment.store', $order), ['method' => 'drop_off'])->assertSessionHas('success');

        Http::assertSent(function (Request $request) use ($order) {
            return $request->method() === 'POST'
                && $request->url() === self::BASE.'/v1/orders'
                && $request->hasHeader('Authorization', 'biteship_test.kunci')
                && $request['reference_id'] === $order->code
                && $request['courier_company'] === 'jne'
                && $request['courier_type'] === 'reg'
                && $request['origin_postal_code'] === 12810
                && $request['destination_postal_code'] === 40132
                && $request['origin_collection_method'] === 'drop_off'
                && $request['destination_contact_name'] === $order->recipient_name
                && $request['items'][0]['weight'] === $order->total_weight;
        });

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('JNE0012345678', $order->tracking_number);
        $this->assertSame('ord_5f1a', $order->shipment_id);
        $this->assertSame(18000, $order->shipment_price);
        $this->assertSame('drop_off', $order->shipment_method);
        $this->assertNotNull($order->shipped_at);

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Cetak label resi')->assertDontSee('Buat pengiriman Biteship</button>', false);

        // Tombol ditekan lagi: tidak memesan kurir kedua kali.
        $this->post(route('admin.orders.shipment.store', $order))->assertSessionHas('error');
        Http::assertSentCount(1);
    }

    public function test_waybill_that_arrives_later_is_picked_up_by_refresh(): void
    {
        Http::fake([
            self::BASE.'/v1/orders' => Http::response($this->biteshipOrder(['courier' => ['waybill_id' => null]]), 200),
            self::BASE.'/v1/orders/ord_5f1a' => Http::response($this->biteshipOrder(['status' => 'allocated']), 200),
        ]);
        $order = $this->order();

        $this->post(route('admin.orders.shipment.store', $order))->assertSessionHas('info');
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame('ord_5f1a', $order->shipment_id);

        $this->post(route('admin.orders.shipment.refresh', $order))->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('JNE0012345678', $order->tracking_number);
        $this->assertSame('allocated', $order->shipment_status);
    }

    public function test_duplicate_reference_adopts_the_existing_shipment(): void
    {
        Http::fake([
            self::BASE.'/v1/orders' => Http::response(['success' => false, 'error' => 'Reference ID already used', 'code' => 40002060, 'details' => ['order_id' => 'ord_lama', 'waybill_id' => 'JNE999']], 400),
            self::BASE.'/v1/orders/ord_lama' => Http::response($this->biteshipOrder(['id' => 'ord_lama', 'courier' => ['waybill_id' => 'JNE999']]), 200),
        ]);
        $order = $this->order();

        $this->post(route('admin.orders.shipment.store', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('ord_lama', $order->shipment_id);
        $this->assertSame('JNE999', $order->tracking_number);
        $this->assertSame(OrderStatus::Shipped, $order->status);
    }

    public function test_failed_shipment_can_be_booked_again_with_a_new_reference(): void
    {
        Http::fake([self::BASE.'/v1/orders' => Http::response($this->biteshipOrder(['id' => 'ord_baru']), 200)]);
        $order = $this->order();
        $order->forceFill(['shipment_id' => 'ord_gagal', 'shipment_status' => 'courier_not_found'])->save();

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Kurir bisa dipesan ulang');
        $this->post(route('admin.orders.shipment.store', $order))->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_starts_with($request['reference_id'], $order->code.'-'));
        $this->assertSame('ord_baru', $order->refresh()->shipment_id);
        $this->assertSame(OrderStatus::Shipped, $order->status);
    }

    public function test_biteship_error_is_shown_and_order_stays_unshipped(): void
    {
        Http::fake([self::BASE.'/v1/orders' => Http::response(['success' => false, 'error' => 'Your balance is not enough', 'code' => 40002051], 400)]);
        $order = $this->order();

        $this->post(route('admin.orders.shipment.store', $order))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Saldo Biteship tidak cukup'));

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNull($order->shipment_id);
        $this->assertNull($order->tracking_number);
    }

    public function test_instant_courier_is_booked_with_coordinates(): void
    {
        Http::fake([self::BASE.'/v1/orders' => Http::response($this->biteshipOrder(['courier' => ['waybill_id' => 'GK-11-123']]), 200)]);
        $order = $this->order(['courier' => 'gojek', 'courier_service' => 'INSTANT', 'destination_latitude' => -6.2001, 'destination_longitude' => 106.8166]);

        $this->post(route('admin.orders.shipment.store', $order), ['method' => 'drop_off'])->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => $request['courier_company'] === 'gojek'
            && $request['courier_type'] === 'instant'
            && $request['origin_collection_method'] === 'pickup'
            && $request['origin_coordinate'] === ['latitude' => -6.2264, 'longitude' => 106.8526]
            && $request['destination_coordinate'] === ['latitude' => -6.2001, 'longitude' => 106.8166]);
    }

    public function test_shipment_is_blocked_when_it_cannot_be_created(): void
    {
        Http::fake();

        // Belum lunas.
        $unpaid = $this->order(['status' => OrderStatus::PendingPayment, 'paid_at' => null]);
        $this->post(route('admin.orders.shipment.store', $unpaid))->assertSessionHas('error');

        // Kurir instan tanpa pin lokasi pembeli.
        $noPin = $this->order(['courier' => 'gojek', 'courier_service' => 'INSTANT']);
        $this->post(route('admin.orders.shipment.store', $noPin))->assertSessionHas('error');
        $this->get(route('admin.orders.show', $noPin))->assertOk()->assertSee('pin lokasi pembeli');

        // Ongkir manual / ambil di toko.
        $manual = $this->order(['courier' => 'manual', 'courier_service' => null]);
        $this->post(route('admin.orders.shipment.store', $manual))->assertSessionHas('error');

        // Penyedia aktif bukan Biteship: kartu tidak tampil.
        config(['shop.shipping.driver' => 'rajaongkir']);
        $order = $this->order();
        $this->post(route('admin.orders.shipment.store', $order))->assertSessionHas('error');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Pengiriman Biteship');

        Http::assertNothingSent();
    }

    public function test_shipping_label_shows_barcode_and_addresses(): void
    {
        $order = $this->order(['status' => OrderStatus::Shipped, 'tracking_number' => 'JNE0012345678', 'shipped_at' => now()]);

        $this->get(route('admin.orders.shipping-label', $order))->assertOk()
            ->assertSee('JNE0012345678')
            ->assertSee('<svg', false)
            ->assertSee($order->recipient_name)
            ->assertSee('Jl. Tebet Raya No. 1');

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Label resi');

        // Tanpa resi tidak ada label.
        $this->get(route('admin.orders.shipping-label', $this->order()))->assertNotFound();
    }

    public function test_store_settings_show_where_the_sender_details_are_set(): void
    {
        $this->actingAs(User::factory()->staff(StaffRole::Owner)->create());

        $this->get(route('admin.store-settings.edit'))->assertOk()
            ->assertSeeInOrder(['Lokasi gudang', 'Alamat lengkap gudang', 'Jl. Tebet Raya No. 1', 'Kode pos gudang', 'Pin lokasi gudang', 'Kontak pengirim untuk kurir', 'Kurir reguler', 'Kurir instan', 'Pelacakan'])
            ->assertSee('Cari alamat di peta')
            ->assertDontSee('wajib terisi sebelum pengiriman Biteship');

        config(['shop.whatsapp' => null]);
        $this->get(route('admin.store-settings.edit'))->assertOk()
            ->assertSee('Isi No. WhatsApp di kartu Kontak')->assertSee('wajib terisi sebelum pengiriman Biteship');
    }

    public function test_only_staff_who_manage_orders_can_create_shipments(): void
    {
        Http::fake();
        $order = $this->order();

        $this->actingAs(User::factory()->staff(StaffRole::CustomerService)->create());
        $this->post(route('admin.orders.shipment.store', $order))->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->post(route('admin.orders.shipment.store', $order))->assertForbidden();
        $this->get(route('admin.orders.shipping-label', $order))->assertForbidden();

        Http::assertNothingSent();
    }
}
