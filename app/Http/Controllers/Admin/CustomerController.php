<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.customers.index', [
            'customers' => User::where('role', UserRole::Customer)
                ->withCount('orders')
                ->withSum(['orders as total_spent' => fn ($q) => $q->whereNotIn('status', [OrderStatus::PendingPayment, OrderStatus::Cancelled])], 'total')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = $request->string('q')->trim()->value();
                    $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"));
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function show(User $customer): View
    {
        abort_unless($customer->isCustomer(), 404);

        return view('admin.customers.show', [
            'customer' => $customer->load('addresses'),
            'orders' => $customer->orders()->latest()->paginate(10),
            'totalSpent' => $customer->orders()
                ->whereNotIn('status', [OrderStatus::PendingPayment, OrderStatus::Cancelled])
                ->sum('total'),
        ]);
    }

    public function toggleActive(User $customer): RedirectResponse
    {
        abort_unless($customer->isCustomer(), 404);

        // is_active sengaja tidak mass-assignable, jadi pakai forceFill.
        $customer->forceFill(['is_active' => ! $customer->is_active])->save();

        return back()->with('success', $customer->is_active ? 'Akun pelanggan diaktifkan.' : 'Akun pelanggan dinonaktifkan.');
    }
}
