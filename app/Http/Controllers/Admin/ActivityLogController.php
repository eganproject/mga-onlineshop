<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Admin → Log Aktivitas: siapa melakukan apa dan kapan. */
class ActivityLogController extends Controller
{
    /** Awalan aksi => label filter. */
    public const GROUPS = [
        'auth' => 'Login',
        'orders' => 'Pesanan',
        'products' => 'Produk',
        'categories' => 'Kategori',
        'stock' => 'Stok',
        'vouchers' => 'Voucher',
        'banners' => 'Banner',
        'reviews' => 'Ulasan',
        'customers' => 'Pelanggan',
        'settings' => 'Pengaturan',
        'staff' => 'Staf',
        'profile' => 'Profil',
    ];

    public function index(Request $request): View
    {
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));
        $group = $request->query('grup');

        return view('admin.activity.index', [
            'logs' => ActivityLog::query()
                ->with('user:id,name,staff_role,role')
                ->when($request->filled('staf'), fn ($q) => $q->where('user_id', $request->integer('staf')))
                ->when(is_string($group) && array_key_exists($group, self::GROUPS), fn ($q) => $q->where('action', 'like', "{$group}.%"))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = $request->string('q')->trim()->value();
                    $q->where(fn ($q) => $q->where('description', 'like', "%{$term}%")->orWhere('subject_label', 'like', "%{$term}%"));
                })
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
                ->latest('created_at')
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'staff' => User::staff()->orderBy('name')->get(['id', 'name']),
            'groups' => self::GROUPS,
        ]);
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
