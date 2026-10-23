<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherType;
use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public const STATES = [
        'running' => 'Berjalan',
        'scheduled' => 'Terjadwal',
        'ended' => 'Berakhir',
        'inactive' => 'Nonaktif',
    ];

    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->value();
        $state = array_key_exists($request->query('status'), self::STATES) ? $request->query('status') : null;

        $vouchers = Voucher::query()
            ->withCount('redemptions')
            ->withSum('redemptions as discount_sum', 'discount')
            ->withSum('redemptions as shipping_discount_sum', 'shipping_discount')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->when($state === 'running', fn ($q) => $q->running())
            ->when($state === 'scheduled', fn ($q) => $q->where('is_active', true)->where('starts_at', '>', now()))
            ->when($state === 'ended', fn ($q) => $q->where('ends_at', '<', now()))
            ->when($state === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.vouchers.index', compact('vouchers', 'search', 'state'));
    }

    public function create(): View
    {
        return view('admin.vouchers.form', [
            'voucher' => new Voucher(['type' => VoucherType::Percent, 'is_active' => true, 'is_public' => true, 'per_user_limit' => 1, 'min_spend' => 0]),
            'used' => 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $voucher = Voucher::create($this->validated($request));

        return redirect()->route('admin.vouchers.index')->with('success', "Voucher {$voucher->code} berhasil dibuat.");
    }

    public function edit(Voucher $voucher): View
    {
        return view('admin.vouchers.form', ['voucher' => $voucher, 'used' => $voucher->redemptions()->count()]);
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        $voucher->update($this->validated($request, $voucher));

        return redirect()->route('admin.vouchers.index')->with('success', "Voucher {$voucher->code} berhasil diperbarui.");
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        // Pesanan lama tetap menyimpan kode & nominal diskonnya (voucher_id menjadi null).
        $voucher->delete();

        return back()->with('success', "Voucher {$voucher->code} dihapus. Riwayat pesanan yang memakainya tetap tersimpan.");
    }

    public function toggle(Voucher $voucher): RedirectResponse
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('success', "Voucher {$voucher->code} ".($voucher->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        $request->merge([
            'code' => Voucher::normalizeCode($request->input('code')),
            // Kolom nominal boleh diisi dengan titik ribuan (25.000).
            ...collect(['value', 'max_discount', 'min_spend'])
                ->mapWithKeys(fn ($key) => [$key => $this->digits($request->input($key))])->all(),
        ]);

        $type = VoucherType::tryFrom((string) $request->input('type'));
        $used = $voucher?->redemptions()->count() ?? 0;

        $data = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vouchers')->ignore($voucher)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(VoucherType::class)],
            'value' => match ($type) {
                VoucherType::Percent => ['required', 'integer', 'between:1,100'],
                VoucherType::Fixed => ['required', 'integer', 'min:1', 'max:100000000'],
                default => ['nullable', 'integer', 'min:0', 'max:100000000'],
            },
            'max_discount' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'min_spend' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], [
            'code.regex' => 'Kode hanya boleh huruf, angka, tanda - dan _.',
            'value.between' => 'Persentase diskon harus 1–100.',
            'ends_at.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ], [
            'code' => 'kode voucher', 'name' => 'nama voucher', 'type' => 'jenis voucher', 'value' => 'nilai',
            'max_discount' => 'maksimal diskon', 'min_spend' => 'minimal belanja', 'quota' => 'kuota',
            'per_user_limit' => 'batas per pelanggan', 'starts_at' => 'tanggal mulai', 'ends_at' => 'tanggal berakhir',
        ]);

        if (isset($data['quota']) && $data['quota'] < $used) {
            throw ValidationException::withMessages([
                'quota' => "Kuota tidak boleh kurang dari pemakaian saat ini ({$used}).",
            ]);
        }

        $data['value'] = (int) ($data['value'] ?? 0);
        $data['min_spend'] = (int) ($data['min_spend'] ?? 0);
        // Batas maksimal diskon hanya berlaku untuk diskon persen.
        $data['max_discount'] = $type === VoucherType::Percent ? ($data['max_discount'] ?? null) : null;
        $data['is_active'] = $request->boolean('is_active');
        $data['is_public'] = $request->boolean('is_public');

        return $data;
    }

    private function digits(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[\s.]/', '', (string) $value);

        return $clean === '' ? null : $clean;
    }
}
