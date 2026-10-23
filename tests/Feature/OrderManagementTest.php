<?php

namespace Tests\Feature;

use App\Contracts\ShipmentTracker;
use App\Data\TrackingResult;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderService;
use App\Services\Shipping\FakeShipmentTracker;
use App\Services\Shipping\RajaOngkirShipmentTracker;
use App\Services\Shipping\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_status_changes_are_recorded_in_history_with_actor_and_note(): void
    {
        $order = $this->makeOrder(OrderStatus::Paid);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'processing', 'note' => 'Sedang dikemas'])
            ->assertRedirect();

        $history = $order->statusHistories()->get();
        $this->assertSame([OrderStatus::Paid, OrderStatus::Processing], $history->pluck('status')->all());
        $this->assertSame(OrderStatus::Paid, $history->last()->from_status);
        $this->assertSame('Sedang dikemas', $history->last()->note);
        $this->assertTrue($history->last()->user->is($this->admin));
        $this->assertNull($history->first()->user_id); // dibuat tanpa login = sistem
    }

    public function test_status_change_dispatches_event(): void
    {
        $order = $this->makeOrder(OrderStatus::Processing);
        Event::fake([OrderStatusChanged::class]);

        app(OrderService::class)->transition($order, OrderStatus::Shipped, 'JNE001');

        Event::assertDispatched(OrderStatusChanged::class, fn ($e) => $e->order->is($order)
            && $e->from === OrderStatus::Processing && $e->to === OrderStatus::Shipped
            && str_contains($e->note, 'JNE001'));
    }

    public function test_full_lifecycle_with_delivered_step(): void
    {
        $order = $this->makeOrder(OrderStatus::Processing);
        $service = app(OrderService::class);

        $service->transition($order, OrderStatus::Shipped, 'JNE001');
        $this->assertFalse($order->status->canTransitionTo(OrderStatus::Completed));

        $service->transition($order, OrderStatus::Delivered);
        $this->assertNotNull($order->delivered_at);

        $service->transition($order, OrderStatus::Completed);
        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_admin_cancel_requires_reason_and_is_idempotent(): void
    {
        $order = $this->makeOrder(OrderStatus::Paid);
        $product = $order->items->first()->product;
        $stock = $product->stock;

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('note');

        $this->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'note' => 'Stok habis di gudang']);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Stok habis di gudang', $order->cancellation_reason);
        $this->assertSame($stock + 2, $product->refresh()->stock);

        // Panggilan ganda tidak mengembalikan stok dua kali.
        app(OrderService::class)->cancel($order, 'lagi');
        $this->assertSame($stock + 2, $product->refresh()->stock);
        $this->assertSame(1, $order->statusHistories()->where('status', OrderStatus::Cancelled)->count());
    }

    public function test_admin_can_fix_tracking_number_after_shipping(): void
    {
        $order = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'SALAH1', 'shipped_at' => now()]);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.tracking-number', $order), ['tracking_number' => 'BENAR2'])
            ->assertSessionHas('success');

        $this->assertSame('BENAR2', $order->refresh()->tracking_number);
        $this->assertStringContainsString('SALAH1', $order->statusHistories()->get()->last()->note);
    }

    public function test_admin_order_filters(): void
    {
        $jne = $this->makeOrder(OrderStatus::Paid, ['courier' => 'jne', 'tracking_number' => 'RESICARI1']);
        $sicepat = $this->makeOrder(OrderStatus::Paid, ['courier' => 'sicepat']);
        $old = $this->makeOrder(OrderStatus::Paid, ['courier' => 'jne']);
        $old->forceFill(['created_at' => now()->subMonth()])->save();

        $this->actingAs($this->admin);

        $this->get(route('admin.orders.index', ['courier' => 'sicepat']))
            ->assertSee($sicepat->code)->assertDontSee($jne->code);

        $this->get(route('admin.orders.index', ['from' => now()->subDays(3)->toDateString()]))
            ->assertSee($jne->code)->assertDontSee($old->code);

        $this->get(route('admin.orders.index', ['q' => 'RESICARI']))
            ->assertSee($jne->code)->assertDontSee($sicepat->code);

        // Tanggal tidak valid diabaikan, bukan error.
        $this->get(route('admin.orders.index', ['from' => 'kemarin']))->assertOk();
    }

    public function test_admin_bulk_process_and_print(): void
    {
        $paid = $this->makeOrder(OrderStatus::Paid);
        $pending = $this->makeOrder(OrderStatus::PendingPayment);

        $this->actingAs($this->admin)
            ->post(route('admin.orders.bulk'), ['action' => 'process', 'orders' => [$paid->id, $pending->id]])
            ->assertSessionHas('success', '1 pesanan diproses. 1 dilewati karena statusnya bukan Dibayar.');

        $this->assertSame(OrderStatus::Processing, $paid->refresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $pending->refresh()->status);

        $this->post(route('admin.orders.bulk'), ['action' => 'packing-slips', 'orders' => [$paid->id, $pending->id]])
            ->assertOk()->assertSee($paid->code)->assertSee($pending->code);
    }

    public function test_admin_print_pages_and_detail_render(): void
    {
        $order = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'JNE777', 'shipped_at' => now()->subHour()]);

        $this->actingAs($this->admin);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Riwayat status')->assertSee('Lacak paket');
        $this->get(route('admin.orders.invoice', $order))->assertOk()->assertSee('INVOICE')->assertSee('LUNAS');
        $this->get(route('admin.orders.packing-slip', $order))->assertOk()->assertSee('JNE777')->assertSee($order->recipient_name);
        $this->get(route('admin.orders.tracking', $order))->assertOk()->assertSee('Paket diterima di counter');
    }

    public function test_customer_can_confirm_received_order(): void
    {
        $order = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'JNE1', 'shipped_at' => now()]);

        $this->actingAs($order->user)
            ->post(route('account.orders.complete', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame('Pelanggan', $order->statusHistories()->with('user')->get()->last()->actorLabel());
    }

    public function test_customer_cannot_confirm_unshipped_or_others_order(): void
    {
        $order = $this->makeOrder(OrderStatus::Processing);

        $this->actingAs($order->user)->post(route('account.orders.complete', $order))->assertSessionHas('error');
        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('account.orders.complete', $order))->assertNotFound();
        $this->actingAs($other)->get(route('account.orders.tracking', $order))->assertNotFound();
        $this->actingAs($other)->get(route('account.orders.invoice', $order))->assertNotFound();
    }

    public function test_customer_cancel_with_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::PendingPayment);

        $this->actingAs($order->user)
            ->post(route('account.orders.cancel', $order), ['reason' => 'Tidak jadi membeli'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertStringContainsString('Tidak jadi membeli', $order->cancellation_reason);

        $other = $this->makeOrder(OrderStatus::PendingPayment);
        $this->actingAs($other->user)
            ->post(route('account.orders.cancel', $other), ['reason' => 'alasan karangan'])
            ->assertSessionHasErrors('reason');
    }

    public function test_customer_order_pages_show_timeline_and_tracking(): void
    {
        $order = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'JNE555', 'shipped_at' => now()->subHours(6)]);

        $this->actingAs($order->user);
        $this->get(route('account.orders.index'))->assertOk()->assertSee('Lacak')->assertSee('JNE555');
        $this->get(route('account.orders.index', ['q' => $order->code]))->assertOk()->assertSee($order->code);
        $this->get(route('account.orders.show', $order))->assertOk()
            ->assertSee('Riwayat pesanan')->assertSee('Pesanan diterima')->assertSee('Sampai');
        $this->get(route('account.orders.tracking', $order))->assertOk()->assertSee('Paket diproses di pusat sortir');
        $this->get(route('account.orders.invoice', $order))->assertOk()->assertSee($order->code);
    }

    public function test_unpaid_order_has_no_invoice_for_customer(): void
    {
        $order = $this->makeOrder(OrderStatus::PendingPayment);

        $this->actingAs($order->user)->get(route('account.orders.invoice', $order))->assertNotFound();
    }

    public function test_fake_tracker_progresses_over_time(): void
    {
        $order = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'X1', 'shipped_at' => now()->subHours(13)]);
        $result = (new FakeShipmentTracker)->track($order);

        $this->assertFalse($result->delivered);
        $this->assertCount(3, $result->events);
        $this->assertSame('Paket dalam perjalanan ke kota tujuan', $result->latest()->description);

        $order->shipped_at = now()->subDays(3);
        $this->assertTrue((new FakeShipmentTracker)->track($order)->delivered);
    }

    public function test_tracking_marks_order_delivered_when_courier_reports_it(): void
    {
        $order = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'X1', 'shipped_at' => now()->subDays(3)]);

        $result = app(TrackingService::class)->track($order);

        $this->assertTrue($result->delivered);
        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertStringContainsString('Kurir melaporkan', $order->statusHistories()->get()->last()->note);
    }

    public function test_tracking_failure_returns_null_and_partial_still_renders(): void
    {
        $this->app->bind(ShipmentTracker::class, fn () => new class implements ShipmentTracker
        {
            public function track(Order $order): TrackingResult
            {
                throw new \RuntimeException('API mati');
            }
        });

        $order = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'X1', 'shipped_at' => now()]);

        $this->assertNull(app(TrackingService::class)->track($order));
        $this->actingAs($order->user)->get(route('account.orders.tracking', $order))
            ->assertOk()->assertSee('Info pelacakan belum tersedia');
    }

    public function test_rajaongkir_tracker_parses_waybill_response(): void
    {
        Http::fake(['*/track/waybill*' => Http::response(['data' => [
            'delivered' => true,
            'summary' => ['courier_code' => 'JNE', 'waybill_number' => 'JNE123', 'status' => 'DELIVERED'],
            'delivery_status' => ['status' => 'DELIVERED', 'pod_receiver' => 'BUDI', 'pod_date' => '2026-10-20', 'pod_time' => '14:05'],
            'manifest' => [
                ['manifest_description' => 'SHIPMENT RECEIVED BY JNE COUNTER', 'manifest_date' => '2026-10-18', 'manifest_time' => '10:00:00', 'city_name' => 'JAKARTA'],
                ['manifest_description' => 'DELIVERED TO [BUDI]', 'manifest_date' => '2026-10-20', 'manifest_time' => '14:05:00', 'city_name' => 'BANDUNG'],
            ],
        ]])]);

        $order = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'JNE123', 'recipient_phone' => '0812-3456-7890']);
        $result = (new RajaOngkirShipmentTracker('key', 'https://rajaongkir.test/api/v1'))->track($order);

        $this->assertTrue($result->delivered);
        $this->assertSame('BUDI', $result->receiver);
        $this->assertSame('DELIVERED TO [BUDI]', $result->latest()->description);
        $this->assertSame('2026-10-20 14:05', $result->deliveredAt->format('Y-m-d H:i'));

        Http::assertSent(fn ($request) => $request->hasHeader('key', 'key')
            && str_contains($request->url(), 'awb=JNE123')
            && str_contains($request->url(), 'courier=jne')
            && str_contains($request->url(), 'last_phone_number=67890'));
    }

    public function test_auto_complete_command(): void
    {
        $delivered = $this->makeOrder(OrderStatus::Delivered, ['delivered_at' => now()->subDays(4), 'shipped_at' => now()->subDays(6)]);
        $fresh = $this->makeOrder(OrderStatus::Delivered, ['delivered_at' => now()->subDay(), 'shipped_at' => now()->subDays(2)]);
        $stuck = $this->makeOrder(OrderStatus::Shipped, ['shipped_at' => now()->subDays(20)]);

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertSame(OrderStatus::Completed, $delivered->refresh()->status);
        $this->assertSame(OrderStatus::Delivered, $fresh->refresh()->status);
        $this->assertSame(OrderStatus::Completed, $stuck->refresh()->status);
    }

    public function test_sync_tracking_command_marks_delivered(): void
    {
        $arrived = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'A1', 'shipped_at' => now()->subDays(3)]);
        $onTheWay = $this->makeOrder(OrderStatus::Shipped, ['tracking_number' => 'A2', 'shipped_at' => now()->subHour()]);

        $this->artisan('orders:sync-tracking')->expectsOutputToContain('1 pesanan sampai tujuan')->assertSuccessful();

        $this->assertSame(OrderStatus::Delivered, $arrived->refresh()->status);
        $this->assertSame(OrderStatus::Shipped, $onTheWay->refresh()->status);
    }

    private function makeOrder(OrderStatus $status, array $attributes = []): Order
    {
        $product = Product::factory()->create(['price' => 10000]);

        $order = Order::factory()->status($status)->create($attributes + [
            'subtotal' => 20000,
            'shipping_cost' => 10000,
            'total' => 30000,
            'shipping_address' => 'Jl. Merdeka 1, Coblong, Kota Bandung, Jawa Barat, 40132',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 10000,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        return $order->load('items.product');
    }
}
