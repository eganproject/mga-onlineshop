<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('account.dashboard', [
            'recentOrders' => $user->orders()->withCount('items')->latest()->take(5)->get(),
            'stats' => [
                'total' => $user->orders()->count(),
                'pending' => $user->orders()->where('status', OrderStatus::PendingPayment)->count(),
                'active' => $user->orders()->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered])->count(),
                'completed' => $user->orders()->where('status', OrderStatus::Completed)->count(),
            ],
            'defaultAddress' => $user->defaultAddress,
        ]);
    }
}
