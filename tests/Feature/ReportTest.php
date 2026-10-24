<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Reports\LowStockReport;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use App\Support\Money;
use App\View\Components\Admin\SalesChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-23 14:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(Product $product, int $qty, string $paidAt, OrderStatus $status = OrderStatus::Completed, int $shipping = 10000): Order
    {
        $order = Order::factory()->status($status)->paidAt($paidAt)->create([
            'subtotal' => $product->price * $qty,
            'shipping_cost' => $shipping,
            'total' => $product->price * $qty + $shipping,
        ]);

        if ($status === OrderStatus::PendingPayment) {
            $order->forceFill(['paid_at' => null])->save();
        }

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->price,
            'quantity' => $qty,
            'subtotal' => $product->price * $qty,
        ]);

        return $order;
    }

    public function test_summary_counts_only_paid_orders_in_period(): void
    {
        $product = Product::factory()->create(['price' => 50000]);

        $this->order($product, 2, '2026-10-20 10:00:00');                                   // 110.000
        $this->order($product, 1, '2026-10-23 08:00:00', OrderStatus::Paid);               // 60.000
        $this->order($product, 5, '2026-10-22 08:00:00', OrderStatus::Cancelled);          // tidak dihitung
        $this->order($product, 3, '2026-10-22 08:00:00', OrderStatus::PendingPayment);     // tidak dihitung
        $this->order($product, 4, '2026-09-01 08:00:00');                                   // di luar periode

        $summary = app(SalesReportService::class)->summary(ReportPeriod::fromPreset('7d'));

        $this->assertSame(170000, $summary['revenue']);
        $this->assertSame(150000, $summary['product_sales']);
        $this->assertSame(20000, $summary['shipping']);
        $this->assertSame(2, $summary['orders']);
        $this->assertSame(3, $summary['items']);
        $this->assertSame(85000, $summary['average']);
        $this->assertSame(1, $summary['cancelled']);
    }

    public function test_timeseries_fills_empty_buckets_and_groups_by_week_and_month(): void
    {
        $product = Product::factory()->create(['price' => 10000]);
        $this->order($product, 1, '2026-10-21 09:00:00', shipping: 0);
        $this->order($product, 2, '2026-10-21 20:00:00', shipping: 0);
        $this->order($product, 1, '2026-10-05 09:00:00', shipping: 0);

        $service = app(SalesReportService::class);

        $daily = $service->timeseries(ReportPeriod::fromPreset('7d'));
        $this->assertCount(7, $daily);
        $this->assertSame('2026-10-17', $daily->first()['key']);
        $tuesday = $daily->firstWhere('key', '2026-10-21');
        $this->assertSame(30000, $tuesday['revenue']);
        $this->assertSame(2, $tuesday['orders']);
        $this->assertSame(3, $tuesday['items']);
        $this->assertSame(0, $daily->firstWhere('key', '2026-10-22')['revenue']);

        $weekly = $service->timeseries(new ReportPeriod(Carbon::parse('2026-10-01')->toImmutable(), Carbon::parse('2026-10-23')->toImmutable(), 'week'));
        $this->assertSame('2026-09-28', $weekly->first()['key']);
        $this->assertStringStartsWith('01 Okt', $weekly->first()['label']); // minggu pertama dipotong di awal periode
        $this->assertSame(40000, $weekly->sum('revenue'));
        $this->assertSame(30000, $weekly->firstWhere('key', '2026-10-19')['revenue']);

        $monthly = $service->timeseries(ReportPeriod::fromPreset('this_year', 'month'));
        $this->assertCount(10, $monthly);
        $this->assertSame(40000, $monthly->last()['revenue']);
    }

    public function test_sales_by_product_and_category(): void
    {
        $parent = Category::factory()->create(['name' => 'Casing HP']);
        $child = Category::factory()->create(['name' => 'Casing iPhone', 'parent_id' => $parent->id]);
        $charger = Category::factory()->create(['name' => 'Charger']);

        $case = Product::factory()->for($child)->create(['name' => 'Casing Clear', 'price' => 50000]);
        $adapter = Product::factory()->for($charger)->create(['name' => 'Adaptor 20W', 'price' => 150000]);
        $gone = Product::factory()->for($charger)->create(['name' => 'Kabel Lama', 'price' => 20000]);

        $this->order($case, 2, '2026-10-22 10:00:00');
        $this->order($case, 1, '2026-10-21 10:00:00');
        $this->order($adapter, 1, '2026-10-22 11:00:00');
        $this->order($gone, 1, '2026-10-22 12:00:00');
        $gone->delete();

        $service = app(SalesReportService::class);
        $period = ReportPeriod::fromPreset('7d');

        $products = $service->byProduct($period);
        $this->assertSame(['Casing Clear', 'Adaptor 20W', 'Kabel Lama'], $products->pluck('name')->all());
        $this->assertSame(3, $products[0]->quantity);
        $this->assertSame(2, $products[0]->orders);
        $this->assertSame(150000, $products[0]->revenue);
        $this->assertNull($products[2]->product_id);
        $this->assertSame(46.9, $products[0]->share);

        $top = $service->byProduct($period, 1);
        $this->assertCount(1, $top);
        $this->assertSame(46.9, $top[0]->share);

        $categories = $service->byCategory($period);
        $this->assertSame(['Charger', 'Casing HP › Casing iPhone', 'Produk dihapus'], $categories->pluck('name')->all());
        $this->assertSame(150000, $categories[0]->revenue);
        $this->assertSame(20000, $categories[2]->revenue);
    }

    public function test_low_stock_report_checks_variants_individually(): void
    {
        Product::factory()->create(['name' => 'Aman', 'stock' => 50]);
        Product::factory()->create(['name' => 'Tipis', 'stock' => 2]);
        Product::factory()->inactive()->create(['name' => 'Nonaktif', 'stock' => 0]);

        $variantProduct = Product::factory()->create(['name' => 'Casing Varian', 'has_variants' => true, 'stock' => 40]);
        ProductVariant::factory()->for($variantProduct)->create(['name' => 'Merah', 'stock' => 0]);
        ProductVariant::factory()->for($variantProduct)->create(['name' => 'Biru', 'stock' => 40]);

        $report = app(LowStockReport::class);
        $items = $report->items();

        $this->assertSame(2, $report->count());
        $this->assertSame(['Casing Varian', 'Tipis'], $items->pluck('name')->all());
        $this->assertSame('Merah', $items[0]->variant->name);
    }

    public function test_dashboard_and_report_pages_render(): void
    {
        $product = Product::factory()->create(['name' => 'Earbuds Pro', 'price' => 250000]);
        $this->order($product, 2, '2026-10-22 10:00:00');

        $this->actingAs($this->admin);

        foreach (['7d', '30d', '90d', 'ngawur'] as $range) {
            $this->get(route('admin.dashboard', ['range' => $range]))->assertOk()->assertSee('Earbuds Pro');
        }

        $this->get(route('admin.reports.index'))->assertOk()->assertSee('Rp510.000');
        $this->get(route('admin.reports.index', ['tab' => 'produk']))->assertOk()->assertSee('Earbuds Pro')
            ->assertSee(route('admin.products.edit', $product), false);
        $this->get(route('admin.dashboard'))->assertSee(route('admin.products.edit', $product), false);
        $this->get(route('admin.reports.index', ['tab' => 'kategori']))->assertOk()->assertSee($product->category->name);
        $this->get(route('admin.reports.index', ['preset' => 'custom', 'from' => '2026-10-23', 'to' => '2026-10-01', 'group' => 'week']))->assertOk();
        $this->get(route('admin.reports.index', ['preset' => 'custom', 'from' => 'bukan-tanggal', 'to' => '2026-13-45']))->assertOk();
        $this->get(route('admin.reports.index', ['preset' => 'this_year', 'group' => 'month']))->assertOk();
        $this->get(route('admin.reports.index', ['preset' => ['x'], 'tab' => ['y'], 'from' => ['z']]))->assertOk();
        $this->get(route('admin.dashboard', ['range' => ['x']]))->assertOk();
    }

    public function test_csv_exports(): void
    {
        $product = Product::factory()->create(['name' => '=HYPERLINK("x")', 'price' => 10000]);
        $order = $this->order($product, 3, '2026-10-22 10:00:00');

        $this->actingAs($this->admin);

        $response = $this->get(route('admin.reports.export', ['type' => 'produk', 'preset' => '7d']));
        $response->assertOk()->assertDownload('laporan-produk_2026-10-17_2026-10-23.csv');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"x\"\")\"", $csv); // formula dinetralkan
        $this->assertStringContainsString(',3,1,30000,100', $csv);

        $csv = $this->get(route('admin.reports.export', ['type' => 'pesanan', 'preset' => '7d']))->streamedContent();
        $this->assertStringContainsString($order->code, $csv);
        $this->assertStringContainsString('2026-10-22 10:00', $csv);

        $csv = $this->get(route('admin.reports.export', ['type' => 'penjualan', 'preset' => '7d']))->streamedContent();
        $this->assertSame(8, substr_count(trim($csv), "\n") + 1); // header + 7 hari

        $this->get(route('admin.reports.export', ['type' => 'kategori']))->assertOk();
        $this->get('/admin/reports/export/rahasia')->assertNotFound();
    }

    public function test_reports_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.reports.index'))->assertForbidden();
        $this->get(route('admin.reports.export', ['type' => 'pesanan']))->assertForbidden();
    }

    public function test_report_period_parsing(): void
    {
        $period = ReportPeriod::fromPreset('last_month');
        $this->assertSame('2026-09-01', $period->from->toDateString());
        $this->assertSame('2026-09-30', $period->to->toDateString());
        $this->assertSame('day', $period->group);
        $this->assertSame('2026-08-02', $period->previous()->from->toDateString());

        $this->assertSame('month', ReportPeriod::fromPreset('this_year')->group);
        $this->assertSame('30d', ReportPeriod::fromPreset('salah')->preset);

        $request = request()->duplicate(['preset' => 'custom', 'from' => '2020-01-01', 'to' => '2026-10-23']);
        $long = ReportPeriod::fromRequest($request);
        $this->assertSame(ReportPeriod::MAX_DAYS, $long->days());
    }

    public function test_money_compact_and_chart_scale(): void
    {
        $this->assertSame('Rp950', Money::compact(950));
        $this->assertSame('Rp12 rb', Money::compact(12000));
        $this->assertSame('Rp1,3 jt', Money::compact(1250000));
        $this->assertSame('Rp2 jt', Money::compact(2000000));
        $this->assertSame('Rp15 jt', Money::compact(15400000));

        $this->assertSame(['max' => 4, 'steps' => 4], SalesChart::scale(0));
        $this->assertSame(['max' => 3, 'steps' => 3], SalesChart::scale(1));
        $this->assertSame(['max' => 8, 'steps' => 4], SalesChart::scale(7));
        $this->assertSame(['max' => 5000000, 'steps' => 5], SalesChart::scale(4600000));
        $this->assertSame(['max' => 1000000, 'steps' => 5], SalesChart::scale(870000));
        $this->assertSame(['max' => 2500000, 'steps' => 5], SalesChart::scale(2100000));
    }

    public function test_demo_seeder_builds_report_data(): void
    {
        $this->seed();

        $this->assertTrue(Order::count() > 50);
        $this->assertTrue(DB::table('order_items')->count() > 50);

        $this->actingAs(User::firstWhere('email', 'admin@mga.test'))
            ->get(route('admin.dashboard'))->assertOk();
    }
}
