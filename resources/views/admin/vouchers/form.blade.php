@php
    use App\Enums\VoucherType;
    $editing = $voucher->exists;
    $type = old('type', $voucher->type?->value ?? 'percent');
    $dt = fn ($field) => old($field, $voucher->{$field}?->format('Y-m-d\TH:i'));
@endphp
<x-layouts.admin :title="$editing ? 'Edit Voucher' : 'Buat Voucher'">
    <x-admin.page-header :title="$editing ? 'Edit voucher '.$voucher->code : 'Buat voucher'" :back="route('admin.vouchers.index')" />

    <form method="POST" action="{{ $editing ? route('admin.vouchers.update', $voucher) : route('admin.vouchers.store') }}"
          class="grid gap-6 lg:grid-cols-3" x-data="{ type: @js($type) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <div class="card space-y-5 p-6">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="code" class="label">Kode voucher</label>
                        <input id="code" name="code" value="{{ old('code', $voucher->code) }}" required maxlength="32" autocomplete="off"
                               placeholder="HEMAT10" class="input font-mono uppercase placeholder:normal-case @error('code') input-error @enderror">
                        <p class="mt-1.5 text-xs text-stone-400">Huruf, angka, - dan _. Otomatis huruf besar.</p>
                        <x-input-error for="code" />
                    </div>
                    <div>
                        <label for="name" class="label">Nama voucher</label>
                        <input id="name" name="name" value="{{ old('name', $voucher->name) }}" required maxlength="100" placeholder="Diskon pengguna baru" class="input @error('name') input-error @enderror">
                        <x-input-error for="name" />
                    </div>
                </div>
                <div>
                    <label for="description" class="label">Keterangan <span class="font-normal text-stone-400">(opsional, untuk catatan admin)</span></label>
                    <input id="description" name="description" value="{{ old('description', $voucher->description) }}" maxlength="255" class="input">
                    <x-input-error for="description" />
                </div>
            </div>

            <div class="card space-y-5 p-6">
                <div>
                    <p class="label">Jenis voucher</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            [VoucherType::Percent, 'Potong sekian % dari subtotal barang'],
                            [VoucherType::Fixed, 'Potong nominal tetap dari subtotal'],
                            [VoucherType::FreeShipping, 'Tanggung ongkir, penuh atau sebagian'],
                        ] as [$case, $hint])
                            <label class="relative block cursor-pointer rounded-xl border-2 p-4 transition"
                                   :class="type === '{{ $case->value }}' ? 'border-brand-500 bg-brand-50/50' : 'border-stone-200 hover:border-stone-300'">
                                <input type="radio" name="type" value="{{ $case->value }}" x-model="type" class="sr-only" @checked($type === $case->value)>
                                <span class="block text-sm font-semibold text-stone-900">{{ $case->label() }}</span>
                                <span class="mt-0.5 block text-xs text-stone-500">{{ $hint }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error for="type" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="value" class="label">
                            <span x-show="type === 'percent'">Persentase diskon (%)</span>
                            <span x-show="type === 'fixed'" x-cloak>Nominal potongan (Rp)</span>
                            <span x-show="type === 'free_shipping'" x-cloak>Maksimal ongkir ditanggung (Rp)</span>
                        </label>
                        <input id="value" name="value" inputmode="numeric" value="{{ old('value', $voucher->value) }}" class="input @error('value') input-error @enderror">
                        <p x-show="type === 'free_shipping'" x-cloak class="mt-1.5 text-xs text-stone-400">Isi 0 untuk gratis ongkir penuh.</p>
                        <x-input-error for="value" />
                    </div>
                    <div x-show="type === 'percent'">
                        <label for="max_discount" class="label">Maksimal diskon (Rp) <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="max_discount" name="max_discount" inputmode="numeric" value="{{ old('max_discount', $voucher->max_discount) }}" placeholder="Tanpa batas" class="input @error('max_discount') input-error @enderror">
                        <x-input-error for="max_discount" />
                    </div>
                    <div>
                        <label for="min_spend" class="label">Minimal belanja (Rp)</label>
                        <input id="min_spend" name="min_spend" inputmode="numeric" value="{{ old('min_spend', $voucher->min_spend) }}" placeholder="0" class="input @error('min_spend') input-error @enderror">
                        <p class="mt-1.5 text-xs text-stone-400">Dihitung dari subtotal barang, sebelum ongkir.</p>
                        <x-input-error for="min_spend" />
                    </div>
                </div>
            </div>

            <div class="card space-y-5 p-6">
                <h2 class="font-bold text-stone-900">Batas pemakaian & periode</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="quota" class="label">Kuota total <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="quota" type="number" min="1" name="quota" value="{{ old('quota', $voucher->quota) }}" placeholder="Tanpa batas" class="input @error('quota') input-error @enderror">
                        @if ($editing)<p class="mt-1.5 text-xs text-stone-400">Sudah dipakai {{ $used }} kali. Pesanan batal mengembalikan kuota.</p>@endif
                        <x-input-error for="quota" />
                    </div>
                    <div>
                        <label for="per_user_limit" class="label">Batas per pelanggan <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="per_user_limit" type="number" min="1" name="per_user_limit" value="{{ old('per_user_limit', $voucher->per_user_limit) }}" placeholder="Tanpa batas" class="input @error('per_user_limit') input-error @enderror">
                        <x-input-error for="per_user_limit" />
                    </div>
                    <div>
                        <label for="starts_at" class="label">Mulai berlaku <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="starts_at" type="datetime-local" name="starts_at" value="{{ $dt('starts_at') }}" class="input @error('starts_at') input-error @enderror">
                        <x-input-error for="starts_at" />
                    </div>
                    <div>
                        <label for="ends_at" class="label">Berakhir <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="ends_at" type="datetime-local" name="ends_at" value="{{ $dt('ends_at') }}" class="input @error('ends_at') input-error @enderror">
                        <x-input-error for="ends_at" />
                    </div>
                </div>
                <p class="text-xs text-stone-400">Waktu mengikuti zona {{ config('app.timezone') }}.</p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card space-y-5 p-6">
                <label class="flex items-center justify-between gap-3">
                    <span><span class="block text-sm font-semibold text-stone-800">Aktif</span><span class="text-xs text-stone-500">Nonaktifkan untuk menghentikan sementara</span></span>
                    <input type="checkbox" name="is_active" value="1" class="checkbox size-5" @checked(old('is_active', $voucher->is_active))>
                </label>
                <label class="flex items-center justify-between gap-3">
                    <span><span class="block text-sm font-semibold text-stone-800">Tampilkan di checkout</span><span class="text-xs text-stone-500">Pelanggan bisa memilih tanpa mengetik kode</span></span>
                    <input type="checkbox" name="is_public" value="1" class="checkbox size-5" @checked(old('is_public', $voucher->is_public))>
                </label>
            </div>
            <div class="rounded-2xl bg-sand-100/70 p-5 text-xs leading-relaxed text-stone-600">
                <p class="font-semibold text-stone-800">Cara kerja</p>
                <ul class="mt-2 list-disc space-y-1 pl-4">
                    <li>Satu pesanan hanya bisa memakai satu voucher.</li>
                    <li>Potongan tidak melebihi subtotal barang (atau ongkir untuk gratis ongkir).</li>
                    <li>Kuota terpakai saat pesanan dibuat dan kembali bila pesanan dibatalkan/kedaluwarsa.</li>
                </ul>
            </div>
            <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan voucher' }}</button>
        </div>
    </form>
</x-layouts.admin>
