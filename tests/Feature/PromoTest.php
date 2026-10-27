<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\VoucherType;
use App\Models\Address;
use App\Models\Banner;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Promo\VoucherService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PromoTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
        $this->address = Address::factory()->for($this->customer)->create();
    }

    /** Isi keranjang (subtotal = harga × jumlah) lalu buka sesi sebagai pelanggan. */
    private function fillCart(int $price = 100000, int $qty = 2, ?User $user = null): Product
    {
        $product = Product::factory()->create(['price' => $price, 'stock' => 50, 'weight' => 300]);
        $this->actingAs($user ?? $this->customer)->post(route('cart.store', $product), ['quantity' => $qty]);

        return $product;
    }

    private function placeOrder(?User $user = null, ?Address $address = null): TestResponse
    {
        return $this->post(route('checkout.store'), ['address_id' => ($address ?? $this->address)->id, 'shipping' => 'jne|REG']);
    }

    // ---------------------------------------------------------------
    // Checkout
    // ---------------------------------------------------------------

    public function test_percent_voucher_with_cap_is_applied_to_order(): void
    {
        $voucher = Voucher::factory()->percent(20, max: 30000)->create(['code' => 'HEMAT20']);
        $this->fillCart(100000, 2); // subtotal 200.000 → 20% = 40.000, dibatasi 30.000

        $this->postJson(route('checkout.voucher.apply'), ['code' => ' hemat20 '])
            ->assertOk()
            ->assertJsonPath('voucher.code', 'HEMAT20')
            ->assertJsonPath('voucher.discount', 30000);

        $this->get(route('checkout.index'))->assertOk()->assertSee('HEMAT20')->assertSee('Diskon voucher');

        $this->placeOrder()->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertSame(200000, $order->subtotal);
        $this->assertSame(30000, $order->discount);
        $this->assertSame(0, $order->shipping_discount);
        $this->assertSame(200000 - 30000 + $order->shipping_cost, $order->total);
        $this->assertSame($voucher->id, $order->voucher_id);
        $this->assertSame('HEMAT20', $order->voucher_code);
        $this->assertSame($order->total, $order->latestPayment->amount);
        $this->assertNull(app(VoucherService::class)->appliedCode(), 'Voucher dilepas setelah pesanan dibuat.');

        $this->get(route('account.orders.show', $order))->assertSee('HEMAT20')->assertSee('−Rp30.000', false);
    }

    public function test_free_shipping_voucher_covers_shipping_up_to_cap(): void
    {
        Voucher::factory()->freeShipping(cap: 5000)->create(['code' => 'ONGKIR']);
        $this->fillCart();

        $this->post(route('checkout.voucher.apply'), ['code' => 'ONGKIR'])->assertSessionHas('success');
        $this->placeOrder()->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertGreaterThan(5000, $order->shipping_cost);
        $this->assertSame(0, $order->discount);
        $this->assertSame(5000, $order->shipping_discount);
        $this->assertSame($order->subtotal + $order->shipping_cost - 5000, $order->total);
    }

    public function test_full_free_shipping_and_fixed_discount_never_exceed_their_base(): void
    {
        $service = app(VoucherService::class);

        $free = Voucher::factory()->freeShipping()->make();
        $this->assertSame(['discount' => 0, 'shipping_discount' => 18000], $service->calculate($free, 50000, 18000));

        $fixed = Voucher::factory()->make(['value' => 80000]);
        $this->assertSame(['discount' => 50000, 'shipping_discount' => 0], $service->calculate($fixed, 50000, 18000));

        // Total bayar minimal Rp1 walau potongan menutup semuanya.
        $this->assertSame(['discount' => 49999, 'shipping_discount' => 0], $service->calculate($fixed, 50000, 0));

        $percent = Voucher::factory()->percent(15)->make();
        $this->assertSame(['discount' => 7499, 'shipping_discount' => 0], $service->calculate($percent, 49999, 0));
    }

    public function test_voucher_rules_are_enforced(): void
    {
        $this->fillCart(50000, 1);
        $apply = fn (string $code) => $this->postJson(route('checkout.voucher.apply'), ['code' => $code]);

        $apply('TIDAKADA')->assertStatus(422)->assertJsonPath('message', 'Kode voucher tidak ditemukan.');

        Voucher::factory()->create(['code' => 'MIN100', 'min_spend' => 100000]);
        $apply('MIN100')->assertStatus(422)->assertJsonFragment(['message' => 'Voucher berlaku untuk belanja minimal Rp100.000. Tambah Rp50.000 lagi untuk memakainya.']);

        Voucher::factory()->create(['code' => 'OFF', 'is_active' => false]);
        $apply('OFF')->assertStatus(422)->assertJsonPath('message', 'Voucher ini sedang tidak aktif.');

        Voucher::factory()->create(['code' => 'LAMA', 'ends_at' => now()->subMinute()]);
        $apply('LAMA')->assertStatus(422)->assertJsonPath('message', 'Masa berlaku voucher sudah berakhir.');

        Voucher::factory()->create(['code' => 'NANTI', 'starts_at' => now()->addDay()]);
        $apply('NANTI')->assertStatus(422);

        $this->assertNull(app(VoucherService::class)->appliedCode());
    }

    public function test_quota_and_per_user_limit_count_only_active_orders(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'SEKALI', 'quota' => 2, 'per_user_limit' => 1]);

        $this->fillCart();
        $this->post(route('checkout.voucher.apply'), ['code' => 'SEKALI']);
        $this->placeOrder()->assertSessionHasNoErrors();
        $first = Order::latest('id')->first();

        // Pelanggan yang sama tidak bisa memakai lagi.
        $this->fillCart();
        $this->postJson(route('checkout.voucher.apply'), ['code' => 'SEKALI'])
            ->assertStatus(422)->assertJsonPath('message', 'Kamu sudah pernah memakai voucher ini.');

        // Pesanan dibatalkan → kuota & jatah pelanggan kembali.
        $this->post(route('account.orders.cancel', $first))->assertSessionHas('success');
        $this->postJson(route('checkout.voucher.apply'), ['code' => 'SEKALI'])->assertOk();
        $this->placeOrder()->assertSessionHasNoErrors();

        // Pelanggan lain memakai sisa kuota, pelanggan ketiga kehabisan.
        foreach ([true, false] as $succeeds) {
            $other = User::factory()->create();
            $address = Address::factory()->for($other)->create();
            $this->fillCart(user: $other);
            $response = $this->postJson(route('checkout.voucher.apply'), ['code' => 'SEKALI']);

            if ($succeeds) {
                $response->assertOk();
                $this->placeOrder(address: $address)->assertSessionHasNoErrors();
            } else {
                $response->assertStatus(422)->assertJsonPath('message', 'Kuota voucher sudah habis.');
            }
        }

        $this->assertSame(2, $voucher->redemptions()->count());
    }

    public function test_voucher_is_rechecked_when_order_is_placed(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'KILAT', 'quota' => 1]);
        $product = $this->fillCart();
        $this->post(route('checkout.voucher.apply'), ['code' => 'KILAT']);

        // Pembeli lain menghabiskan kuota sebelum pelanggan ini menekan "Buat pesanan".
        Order::factory()->unpaid()->create(['voucher_id' => $voucher->id, 'voucher_code' => 'KILAT']);

        $this->from(route('checkout.index'))->placeOrder()
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['voucher' => 'Kuota voucher sudah habis.']);

        $this->assertSame(1, Order::count());
        $this->assertSame(50, $product->refresh()->stock, 'Stok tidak berkurang bila checkout gagal.');

        // Halaman checkout melepas voucher yang tidak valid dan menjelaskan alasannya.
        $this->get(route('checkout.index'))->assertOk()->assertSee('Voucher KILAT dilepas');
        $this->assertNull(app(VoucherService::class)->appliedCode());
    }

    public function test_voucher_can_be_removed_and_public_vouchers_are_suggested(): void
    {
        Voucher::factory()->create(['code' => 'PUBLIK', 'is_public' => true, 'name' => 'Untuk semua']);
        Voucher::factory()->create(['code' => 'RAHASIA', 'is_public' => false]);
        Voucher::factory()->create(['code' => 'BESAR', 'is_public' => true, 'min_spend' => 1000000]);
        $this->fillCart();

        $this->get(route('checkout.index'))
            ->assertSee('PUBLIK')
            ->assertSee('BESAR')
            ->assertSee('Tambah Rp800.000 lagi')
            ->assertDontSee('RAHASIA');

        $this->postJson(route('checkout.voucher.apply'), ['code' => 'RAHASIA'])->assertOk();
        $this->deleteJson(route('checkout.voucher.remove'))->assertOk();
        $this->assertNull(app(VoucherService::class)->appliedCode());

        $this->placeOrder();
        $this->assertSame(0, Order::first()->discount);
    }

    public function test_midtrans_items_include_voucher_discount(): void
    {
        config(['shop.payment.driver' => 'midtrans', 'services.midtrans.server_key' => 'SB-Mid-server-test']);
        Http::fake(['*/snap/v1/transactions' => Http::response(['token' => 'snap-1', 'redirect_url' => 'https://pay.test/1'], 201)]);

        Voucher::factory()->create(['code' => 'POTONG', 'value' => 15000]);
        $this->fillCart();
        $this->post(route('checkout.voucher.apply'), ['code' => 'POTONG']);
        $this->placeOrder()->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertSame(15000, $order->discount);

        Http::assertSent(fn (Request $request) => $request['transaction_details']['gross_amount'] === $order->total
            && array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $request['item_details'])) === $order->total
            && collect($request['item_details'])->contains(fn ($i) => $i['id'] === 'VOUCHER' && $i['price'] === -15000));
    }

    // ---------------------------------------------------------------
    // Laporan
    // ---------------------------------------------------------------

    public function test_reports_include_voucher_discounts(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->create(['subtotal' => 100000, 'shipping_cost' => 10000, 'discount' => 10000, 'total' => 100000, 'voucher_code' => 'HEMAT']);
        Order::factory()->create(['subtotal' => 50000, 'shipping_cost' => 10000, 'shipping_discount' => 10000, 'total' => 50000, 'voucher_code' => 'ONGKIR']);
        Order::factory()->create(['subtotal' => 80000, 'shipping_cost' => 10000, 'total' => 90000]);
        Order::factory()->status(OrderStatus::Cancelled)->create(['discount' => 5000, 'voucher_code' => 'HEMAT']);

        $period = ReportPeriod::fromPreset('7d');
        $reports = app(SalesReportService::class);
        $summary = $reports->summary($period);

        $this->assertSame(240000, $summary['revenue']);
        $this->assertSame(230000, $summary['product_sales']);
        $this->assertSame(20000, $summary['discount']);
        $this->assertEqualsCanonicalizing(
            [['HEMAT', 1, 10000], ['ONGKIR', 1, 10000]],
            $reports->byVoucher($period)->map(fn ($r) => [$r->voucher_code, $r->orders, $r->discount])->all(),
        );

        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee('Pemakaian voucher')->assertSee('ONGKIR');

        $csv = $this->get(route('admin.reports.export', 'pesanan'))->streamedContent();
        $this->assertStringContainsString('Voucher,"Diskon (Rp)"', $csv);
        $this->assertStringContainsString(',HEMAT,10000,0,0,0,100000,', $csv);
    }

    // ---------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------

    public function test_admin_manages_vouchers(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.vouchers.index'))->assertOk()->assertSee('Belum ada voucher');
        $this->get(route('admin.vouchers.create'))->assertOk();

        $this->post(route('admin.vouchers.store'), [
            'code' => 'hemat!25', 'name' => 'Hemat', 'type' => 'percent', 'value' => '150',
        ])->assertSessionHasErrors(['code', 'value']);

        $this->post(route('admin.vouchers.store'), [
            'code' => 'hemat25', 'name' => 'Hemat 25%', 'type' => 'percent', 'value' => '25',
            'max_discount' => '50.000', 'min_spend' => '200.000', 'quota' => 100, 'per_user_limit' => 1,
            'starts_at' => '2026-10-01T00:00', 'ends_at' => '2026-12-31T23:59', 'is_active' => 1, 'is_public' => 1,
        ])->assertRedirect(route('admin.vouchers.index'));

        $voucher = Voucher::firstWhere('code', 'HEMAT25');
        $this->assertSame(VoucherType::Percent, $voucher->type);
        $this->assertSame(50000, $voucher->max_discount);
        $this->assertSame(200000, $voucher->min_spend);
        $this->assertTrue($voucher->is_public);

        $this->get(route('admin.vouchers.index'))->assertOk()->assertSee('HEMAT25')->assertSee('Diskon 25% s.d. Rp50.000');
        $this->get(route('admin.vouchers.edit', $voucher))->assertOk()->assertSee('2026-12-31T23:59');

        // Kuota tidak bisa di bawah pemakaian; max_discount dibuang untuk jenis selain persen.
        Order::factory()->count(3)->create(['voucher_id' => $voucher->id]);
        $this->put(route('admin.vouchers.update', $voucher), ['code' => 'HEMAT25', 'name' => 'X', 'type' => 'fixed', 'value' => '10000', 'max_discount' => '5000', 'quota' => 2])
            ->assertSessionHasErrors('quota');
        $this->put(route('admin.vouchers.update', $voucher), ['code' => 'HEMAT25', 'name' => 'X', 'type' => 'fixed', 'value' => '10.000', 'max_discount' => '5000', 'quota' => 3])
            ->assertSessionHasNoErrors();
        $this->assertNull($voucher->refresh()->max_discount);
        $this->assertFalse($voucher->is_active);

        $this->patch(route('admin.vouchers.toggle', $voucher));
        $this->assertTrue($voucher->refresh()->is_active);
        $this->get(route('admin.vouchers.index', ['status' => 'running']))->assertSee('Kuota habis');

        $order = Order::first();
        $order->update(['voucher_code' => 'HEMAT25']);
        $this->delete(route('admin.vouchers.destroy', $voucher))->assertSessionHas('success');
        $this->assertNull($order->refresh()->voucher_id);
        $this->assertSame('HEMAT25', $order->voucher_code);
    }

    public function test_admin_manages_banners_and_homepage_shows_visible_ones(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.banners.index'))->assertOk()->assertSee('Belum ada banner');

        $this->post(route('admin.banners.store'), ['title' => 'Promo', 'link_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors(['image', 'link_url']);
        $this->post(route('admin.banners.store'), ['title' => 'Promo', 'link_url' => '//evil.test', 'image' => UploadedFile::fake()->image('b.jpg', 1600, 700)])
            ->assertSessionHasErrors('link_url');

        $this->post(route('admin.banners.store'), [
            'title' => 'Diskon Casing', 'subtitle' => 'Pakai kode HEMAT10', 'button_label' => 'Belanja',
            'link_url' => '/produk?promo=1', 'image' => UploadedFile::fake()->image('b.jpg', 1600, 700),
            'mobile_image' => UploadedFile::fake()->image('m.jpg', 800, 1000), 'is_active' => 1, 'show_text' => 1,
        ])->assertRedirect(route('admin.banners.index'));

        $banner = Banner::first();
        Storage::disk('public')->assertExists([$banner->image_path, $banner->mobile_image_path]);

        Banner::factory()->create(['title' => 'Banner Terjadwal', 'starts_at' => now()->addDay()]);
        Banner::factory()->create(['title' => 'Banner Mati', 'is_active' => false]);

        $this->get(route('admin.banners.index'))->assertOk()->assertSee('Diskon Casing')->assertSee('Terjadwal');
        $this->get(route('admin.banners.edit', $banner))->assertOk();

        $this->get(route('home'))->assertOk()
            ->assertSee('Diskon Casing')
            ->assertSee(url('/produk?promo=1'), false)
            ->assertSee($banner->mobileImageUrl(), false)
            ->assertDontSee('Banner Terjadwal')
            ->assertDontSee('Banner Mati');

        $old = $banner->mobile_image_path;
        $this->put(route('admin.banners.update', $banner), ['title' => 'Diskon Casing', 'remove_mobile_image' => 1, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);
        $this->assertNull($banner->refresh()->mobile_image_path);

        $this->patch(route('admin.banners.toggle', $banner));
        $this->get(route('home'))->assertDontSee('Diskon Casing');

        $this->delete(route('admin.banners.destroy', $banner));
        Storage::disk('public')->assertMissing($banner->image_path);
        $this->assertSame(2, Banner::count());
    }

    public function test_promo_admin_pages_are_admin_only(): void
    {
        $this->actingAs($this->customer);

        $this->get(route('admin.vouchers.index'))->assertForbidden();
        $this->get(route('admin.banners.index'))->assertForbidden();
    }
}
