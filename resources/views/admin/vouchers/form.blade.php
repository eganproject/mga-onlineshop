@php
    use App\Enums\VoucherType;
    $editing = $voucher->exists;
    $type = old('type', $voucher->type?->value ?? 'percent');
@endphp
<x-layouts.admin :title="$editing ? 'Edit Voucher' : 'Buat Voucher'">
    <x-admin.page-header :title="$editing ? 'Edit voucher '.$voucher->code : 'Buat voucher'" :back="route('admin.vouchers.index')" />

    <form method="POST" action="{{ $editing ? route('admin.vouchers.update', $voucher) : route('admin.vouchers.store') }}"
          class="grid grid-cols-1 gap-6 lg:grid-cols-3" x-data="{ type: @js($type) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <div class="card space-y-5 p-6">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <div class="label-row">
                            <label for="code" class="label">Kode voucher</label>
                            <x-tooltip label="Kode voucher" text="Kode yang diketik pelanggan di kolom voucher saat checkout."
                                       tips="Pendek & mudah diingat, mis. HEMAT10 atau ONGKIRGRATIS. Hindari huruf yang mirip seperti O dan 0." />
                        </div>
                        <input id="code" name="code" value="{{ old('code', $voucher->code) }}" required maxlength="32" autocomplete="off"
                               placeholder="HEMAT10" class="input font-mono uppercase placeholder:normal-case @error('code') input-error @enderror">
                        <p class="mt-1.5 text-xs text-stone-400">Huruf, angka, - dan _. Otomatis huruf besar.</p>
                        <x-input-error for="code" />
                    </div>
                    <div>
                        <div class="label-row">
                            <label for="name" class="label">Nama voucher</label>
                            <x-tooltip label="Nama voucher" text="Nama yang dilihat pelanggan di daftar voucher checkout, mis. “Diskon 10% pengguna baru”." />
                        </div>
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
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
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

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <div class="label-row">
                            <label for="value" class="label">
                                <span x-show="type === 'percent'">Persentase diskon (%)</span>
                                <span x-show="type === 'fixed'" x-cloak>Nominal potongan (Rp)</span>
                                <span x-show="type === 'free_shipping'" x-cloak>Maksimal ongkir ditanggung (Rp)</span>
                            </label>
                            <x-tooltip label="Nilai voucher" text="Persen: potongan sekian % dari subtotal barang. Nominal: potongan rupiah tetap. Gratis ongkir: batas ongkir yang ditanggung toko (0 = gratis penuh)." />
                        </div>
                        <div class="relative" x-data>
                            <span class="input-addon" x-show="type !== 'percent'" x-cloak>Rp</span>
                            <span class="input-addon right-3.5 left-auto" x-show="type === 'percent'">%</span>
                            <input id="value" name="value" x-money value="{{ \App\Support\FormField::thousands(old('value', $voucher->value)) }}"
                                   :class="type === 'percent' ? 'pr-10' : 'pl-10'" class="input tabular-nums @error('value') input-error @enderror">
                        </div>
                        <p x-show="type === 'free_shipping'" x-cloak class="mt-1.5 text-xs text-stone-400">Isi 0 untuk gratis ongkir penuh.</p>
                        <x-input-error for="value" />
                    </div>
                    <div x-show="type === 'percent'">
                        <x-form.money name="max_discount" label="Maksimal diskon" optional :value="$voucher->max_discount" placeholder="Tanpa batas"
                                      tooltip="Batas potongan untuk voucher persen. Contoh: 10% maks. Rp20.000, belanja Rp500.000 tetap dipotong Rp20.000 (bukan Rp50.000)."
                                      tooltip-tips="Selalu isi untuk voucher persen supaya potongan di pesanan besar tetap terkendali." />
                    </div>
                    <x-form.money name="min_spend" label="Minimal belanja" :value="$voucher->min_spend" placeholder="0"
                                  hint="Dihitung dari subtotal barang, sebelum ongkir."
                                  tooltip="Voucher baru bisa dipakai bila subtotal barang di keranjang mencapai angka ini. Isi 0 untuk tanpa minimal."
                                  tooltip-tips="Pasang sedikit di atas rata-rata belanja pelanggan untuk mendorong mereka menambah barang." />
                </div>
            </div>

            <div class="card space-y-5 p-6">
                <h2 class="font-bold text-stone-900">Batas pemakaian & periode</h2>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-form.number name="quota" label="Kuota total" optional :value="$voucher->quota" min="1" placeholder="Tanpa batas"
                                   :hint="$editing ? 'Sudah dipakai '.$used.' kali. Pesanan batal mengembalikan kuota.' : null"
                                   tooltip="Jumlah maksimal voucher bisa dipakai oleh semua pelanggan (siapa cepat dia dapat). Kosongkan untuk tanpa batas."
                                   tooltip-tips="Kuota terpakai saat pesanan dibuat dan kembali otomatis bila pesanan batal/kedaluwarsa." />
                    <x-form.number name="per_user_limit" label="Batas per pelanggan" optional :value="$voucher->per_user_limit" min="1" placeholder="Tanpa batas"
                                   tooltip="Berapa kali satu akun pelanggan boleh memakai voucher ini. Kosongkan untuk tanpa batas."
                                   tooltip-tips="Isi 1 untuk voucher sekali pakai, mis. khusus pembeli baru." />
                    <x-form.date name="starts_at" label="Mulai berlaku" optional time :value="$voucher->starts_at"
                                 tooltip="Di luar rentang Mulai–Berakhir, voucher ditolak di checkout dan tidak ditawarkan. Kosongkan untuk langsung berlaku/tanpa batas waktu." />
                    <x-form.date name="ends_at" label="Berakhir" optional time :value="$voucher->ends_at" />
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
                    <span><span class="flex items-center gap-1 text-sm font-semibold text-stone-800">Tampilkan di checkout <x-tooltip label="Tampilkan di checkout" text="Bila mati, voucher tersembunyi: hanya bisa dipakai dengan mengetik kodenya. Cocok untuk kode khusus reseller, member, atau influencer." /></span><span class="text-xs text-stone-500">Pelanggan bisa memilih tanpa mengetik kode</span></span>
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
