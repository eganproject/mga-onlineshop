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
            ->when($request->query('status') === 'diblokir', fn ($q) => $q->where('is_active', false))
            // Pernah belanja = punya pesanan yang dibayar (bukan menunggu bayar / batal).
            ->when($request->query('belanja') === 'pernah', fn ($q) => $q->whereHas('orders', fn ($o) => $o->whereNotIn('status', [OrderStatus::PendingPayment, OrderStatus::Cancelled])))
            ->when($request->query('belanja') === 'belum', fn ($q) => $q->whereDoesntHave('orders', fn ($o) => $o->whereNotIn('status', [OrderStatus::PendingPayment, OrderStatus::Cancelled])))
            ->when($request->query('login') === 'google', fn ($q) => $q->whereNotNull('firebase_uid'))
            ->when($request->query('login') === 'email', fn ($q) => $q->whereNull('firebase_uid'))
            ->when($this->date($request->query('from')), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($this->date($request->query('to')), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()));

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

    /** Tanggal filter (Y-m-d) yang valid, selain itu diabaikan. */
    private function date(mixed $value): ?\Illuminate\Support\Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
