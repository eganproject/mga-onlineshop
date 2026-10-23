<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $paidStatuses = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

        $revenueThisMonth = Order::whereIn('status', $paidStatuses)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('total');

        $revenueLastMonth = Order::whereIn('status', $paidStatuses)
            ->whereBetween('paid_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->sum('total');

        // Pendapatan 7 hari terakhir untuk grafik batang sederhana.
        $daily = Order::whereIn('status', $paidStatuses)
            ->where('paid_at', '>=', now()->subDays(6)->startOfDay())
            ->get(['paid_at', 'total'])
            ->groupBy(fn (Order $o) => $o->paid_at->toDateString())
            ->map->sum('total');

        $chart = collect(range(6, 0))->map(function (int $daysAgo) use ($daily) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'label' => $date->translatedFormat('D'),
                'date' => $date->translatedFormat('d M'),
                'value' => (int) ($daily[$date->toDateString()] ?? 0),
            ];
        });

        return view('admin.dashboard', [
            'stats' => [
                'revenue' => (int) $revenueThisMonth,
                'revenue_change' => $revenueLastMonth > 0
                    ? round(($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth * 100, 1)
                    : null,
                'orders_today' => Order::whereDate('created_at', today())->count(),
                'to_process' => Order::whereIn('status', [OrderStatus::Paid, OrderStatus::Processing])->count(),
                'customers' => User::where('role', UserRole::Customer)->count(),
                'new_customers' => User::where('role', UserRole::Customer)->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'chart' => $chart,
            'chartMax' => max(1, $chart->max('value')),
            'recentOrders' => Order::with('user')->latest()->take(6)->get(),
            'lowStock' => Product::where('stock', '<=', config('shop.low_stock_threshold'))
                ->where('is_active', true)->orderBy('stock')->take(5)->get(),
        ]);
    }
}
