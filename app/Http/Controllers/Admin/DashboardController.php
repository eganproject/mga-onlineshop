<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\LowStockReport;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Pilihan periode di dashboard (subset dari preset laporan). */
    public const RANGES = ['7d' => '7 hari', '30d' => '30 hari', '90d' => '90 hari'];

    public function __invoke(Request $request, SalesReportService $reports, LowStockReport $lowStock): View
    {
        $range = ReportPeriod::param($request, 'range');
        $range = $range && array_key_exists($range, self::RANGES) ? $range : '30d';
        $period = ReportPeriod::fromPreset($range, $range === '90d' ? 'week' : 'day');

        // Angka penjualan & stok hanya dihitung untuk jabatan yang boleh melihatnya.
        $canReports = $request->user()->can('reports.view');
        $canStock = $request->user()->can('stock.manage');
        $summary = $canReports ? $reports->summary($period) : null;

        $open = Order::query()
            ->whereIn('status', [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped])
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.dashboard', [
            'range' => $range,
            'period' => $period,
            'summary' => $summary,
            'changes' => $canReports ? $reports->compare($summary, $reports->summary($period->previous())) : null,
            'series' => $canReports ? $reports->timeseries($period) : null,
            'topProducts' => $canReports ? $reports->byProduct($period, 5) : collect(),
            'categories' => $canReports ? $reports->byCategory($period)->take(5) : collect(),
            'pipeline' => collect([OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped])
                ->map(fn (OrderStatus $s) => ['status' => $s, 'count' => (int) ($open[$s->value] ?? 0)]),
            'customers' => [
                'total' => User::where('role', UserRole::Customer)->count(),
                'new' => User::where('role', UserRole::Customer)->whereBetween('created_at', [$period->start(), $period->end()])->count(),
            ],
            'ordersToday' => Order::whereDate('created_at', today())->count(),
            'recentOrders' => Order::with('user:id,name')->latest()->take(6)->get(),
            'lowStock' => $canStock ? $lowStock->items(6) : collect(),
            'lowStockCount' => $canStock ? $lowStock->count() : 0,
        ]);
    }
}
