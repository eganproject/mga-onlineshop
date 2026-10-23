<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::where('role', UserRole::Customer)
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereNotIn('status', [OrderStatus::PendingPayment, OrderStatus::Cancelled])], 'total')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->trim()->value();
                $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"));
            })
            ->when($request->query('status') === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'diblokir', fn ($q) => $q->where('is_active', false));

        AdminList::sort($query, $request, [
            'nama' => 'name',
            'pesanan' => 'orders_count',
            'belanja' => 'total_spent',
            'terdaftar' => fn ($q, $dir) => $q->orderBy('created_at', $dir)->orderBy('id', $dir),
        ], '-terdaftar');

        return view('admin.customers.index', [
            'customers' => AdminList::paginate($query, $request),
        ]);
    }

    public function show(Request $request, User $customer): View
    {
        abort_unless($customer->isCustomer(), 404);

        return view('admin.customers.show', [
            'customer' => $customer->load('addresses'),
            'orders' => AdminList::paginate($customer->orders()->latest(), $request, 10),
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
