@php
    use App\Enums\PaymentMethod;
    use App\Services\Billing\BillingSettings;

    $val = fn (string $field) => old($field, $values[$field] ?? null);
    $toggle = function (string $name, string $title, string $text) use ($val) {
        return compact('name', 'title', 'text') + ['checked' => (bool) $val($name)];
    };
    $groups = collect(PaymentMethod::cases())->groupBy(fn (PaymentMethod $m) => $m->group());
@endphp
<x-layouts.admin title="Pajak & Biaya">
    <x-admin.page-header title="Pajak & Biaya" description="PPN, biaya layanan, pembulatan, biaya transaksi Midtrans, dan invoice. Perubahan berlaku untuk pesanan baru; pesanan lama menyimpan angkanya sendiri." />

    <form method="POST" action="{{ route('admin.billing.update') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-3"
          x-data="billingPreview({ url: @js(route('admin.billing.preview')) })">
        @csrf
        @method('PUT')

        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-form.errors />

            {{-- PPN --}}
            <section class="card space-y-5 p-6" x-data="{ tax: @js((bool) $val('tax_enabled')), dpp: @js($val('tax_dpp')), rate: @js((string) $val('tax_rate')) }">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="percent" /></span>
                    <div class="min-w-0"><h2 class="font-bold text-stone-900">Pajak (PPN)</h2><p class="text-xs text-stone-500">Dihitung di checkout, ditampilkan di invoice, dan dikirim ke Midtrans sebagai rincian tagihan.</p></div>
                </div>

                <x-form.switch name="tax_enabled" label="Kenakan PPN pada penjualan" card x-model="tax" :checked="$val('tax_enabled')"
                               description="Aktifkan bila toko sudah dikukuhkan sebagai Pengusaha Kena Pajak (PKP)." />

                <div x-show="tax" @unless ($val('tax_enabled')) x-cloak @endunless class="space-y-5">
                    @unless ($val('is_pkp'))
                        <div class="flex gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                            <x-icon name="warning" class="size-4 shrink-0" />
                            <p>Status PKP di bagian “Penerbit invoice” belum dicentang. Hanya PKP yang boleh memungut PPN; centang bila toko sudah PKP.</p>
                        </div>
                    @endunless

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-form.input name="tax_label" label="Nama pajak" :value="$val('tax_label')" maxlength="20" required
                                      tooltip="Nama yang tampil di checkout, invoice, dan rincian Midtrans, mis. “PPN”." />
                        <x-form.number name="tax_rate" label="Tarif" :value="$val('tax_rate')" min="0" max="100" step="0.01" suffix="%" required x-on:input="rate = $event.target.value"
                                       tooltip="Tarif PPN. Sejak 2025 tarif umum 12% dengan DPP nilai lain 11/12, jadi beban efektif tetap 11%."
                                       :tooltip-tips="['Pilih 12% + DPP 11/12 untuk barang non-mewah sesuai PMK 131/2024.', 'Atau 11% + DPP harga jual bila konsultan pajak menyarankan begitu; hasilnya sama.']" />
                    </div>

                    <div>
                        <div class="label-row">
                            <p class="label">Dasar pengenaan pajak (DPP)</p>
                            <x-tooltip label="DPP" text="Nilai yang dikalikan tarif PPN." :tips="['Harga jual: DPP = harga setelah diskon.', 'Nilai lain 11/12: DPP = 11/12 × harga jual (aturan tarif 12% mulai 2025).']" />
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach (['full' => ['Harga jual', 'DPP = harga setelah diskon'], 'other' => ['Nilai lain 11/12', 'DPP = 11/12 × harga jual']] as $value => [$title, $text])
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 p-3 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="tax_dpp" value="{{ $value }}" x-model="dpp" class="mt-0.5" @checked($val('tax_dpp') === $value)>
                                    <span><span class="block text-sm font-semibold text-stone-800">{{ $title }}</span><span class="mt-0.5 block text-xs leading-relaxed text-stone-500">{{ $text }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        <p class="hint" x-show="rate" x-text="'Beban efektif: ' + (Math.round((parseFloat(String(rate).replace(',', '.')) || 0) * (dpp === 'other' ? 11 / 12 : 1) * 100) / 100).toLocaleString('id-ID') + '% dari harga jual.'"></p>
                    </div>

                    <div>
                        <div class="label-row">
                            <p class="label">Cara hitung</p>
                            <x-tooltip label="Cara hitung" text="Menentukan apakah PPN menambah tagihan pembeli atau sudah ada di dalam harga produk." />
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ([
                                'exclusive' => ['Ditambahkan ke pembeli', 'Harga produk belum termasuk PPN. PPN muncul sebagai baris tambahan di checkout.'],
                                'inclusive' => ['Sudah termasuk harga', 'Harga produk sudah termasuk PPN. Total tidak berubah, PPN hanya dirinci di invoice.'],
                            ] as $value => [$title, $text])
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 p-3 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="tax_mode" value="{{ $value }}" class="mt-0.5" @checked($val('tax_mode') === $value)>
                                    <span><span class="block text-sm font-semibold text-stone-800">{{ $title }}</span><span class="mt-0.5 block text-xs leading-relaxed text-stone-500">{{ $text }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ([
                            $toggle('tax_shipping', 'PPN juga atas ongkir', 'Ongkir (setelah potongan) ikut dikenai PPN. Umumnya tidak, karena ongkir diteruskan ke kurir.'),
                            $toggle('tax_price_note', 'Tampilkan catatan di harga', 'Mis. “Harga belum termasuk PPN 11%” di halaman produk & keranjang.'),
                        ] as $item)
                            <x-form.switch card :name="$item['name']" :label="$item['title']" :description="$item['text']" :checked="$item['checked']" />
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Biaya layanan & pembulatan --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-600"><x-icon name="receipt" /></span>
                    <div class="min-w-0"><h2 class="font-bold text-stone-900">Biaya layanan & pembulatan</h2><p class="text-xs text-stone-500">Biaya tambahan per pesanan dan pembulatan total tagihan.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-form.money name="service_fee" label="Biaya layanan per pesanan" :value="$val('service_fee')"
                                  hint="Isi 0 untuk tanpa biaya layanan."
                                  tooltip="Biaya tetap yang ditambahkan ke setiap pesanan (mis. biaya packing/penanganan). Ikut dikenai PPN bila PPN aktif." />
                    <x-form.input name="service_fee_label" label="Nama biaya layanan" :value="$val('service_fee_label')" maxlength="40" required />
                    <x-form.select name="rounding_mode" label="Pembulatan total" :search="false" :selected="$val('rounding_mode')"
                                   :options="['none' => 'Tanpa pembulatan', 'nearest' => 'Ke terdekat', 'up' => 'Selalu ke atas', 'down' => 'Selalu ke bawah']"
                                   tooltip="Total tagihan dibulatkan ke kelipatan di samping. Selisihnya tampil sebagai baris “Pembulatan” di checkout & invoice."
                                   :tooltip-tips="['“Ke bawah” = toko menanggung selisih kecil, pembeli senang.', 'Pembulatan tidak mengubah PPN yang tercatat.']" />
                    <x-form.select name="rounding_unit" label="Kelipatan" :search="false" :selected="(string) $val('rounding_unit')" :options="\App\Http\Controllers\Admin\BillingSettingController::ROUNDING_UNITS" />
                </div>
            </section>

            {{-- Biaya transaksi Midtrans --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><x-icon name="credit-card" /></span>
                    <div class="min-w-0"><h2 class="font-bold text-stone-900">Biaya transaksi pembayaran (Midtrans)</h2><p class="text-xs text-stone-500">Tarif potongan Midtrans per metode, dan siapa yang menanggungnya.</p></div>
                </div>

                <div class="flex gap-2 rounded-xl bg-sky-50 p-3 text-xs text-sky-800">
                    <x-icon name="info" class="size-4 shrink-0" />
                    <p>Angka awal adalah tarif standar Midtrans (belum PPN). Samakan dengan kontrak/dashboard Midtrans toko. Bila ada metode yang <strong>ditanggung pembeli</strong>, pembeli memilih metode dulu di halaman bayar supaya biayanya terlihat sebelum membayar. Metode yang dimatikan tidak muncul di Midtrans.</p>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-form.input name="payment_fee_label" label="Nama biaya di tagihan" :value="$val('payment_fee_label')" maxlength="40" required wrapper-class="sm:col-span-1"
                                  tooltip="Nama baris biaya yang dilihat pembeli, mis. “Biaya transaksi” atau “Biaya admin”." />
                    <x-form.number name="payment_fee_vat" label="PPN atas biaya Midtrans" :value="$val('payment_fee_vat')" min="0" max="100" step="0.01" suffix="%"
                                   tooltip="Midtrans menagih PPN atas biayanya. Isi 11 bila tarif di bawah belum termasuk PPN, supaya perkiraan potongan & biaya pembeli akurat."
                                   hint="Isi 0 bila tarif sudah termasuk PPN." />
                    <x-form.switch name="payment_fee_gross_up" label="Toko terima utuh" card wrapper-class="sm:mt-6 sm:self-start" :checked="$val('payment_fee_gross_up')"
                                   description="Hitung biaya persen secara gross-up."
                                   tooltip="Untuk metode yang ditanggung pembeli: biaya dihitung dari total yang sudah termasuk biaya itu sendiri, jadi setelah dipotong Midtrans dana toko tetap sama dengan total pesanan." />
                </div>

                <x-input-error for="methods" />
                {{-- Tiap metode = satu baris. Lebar cukup: tampil seperti tabel; sempit (HP): label di atas tiap isian. --}}
                <div class="@container -mx-6 border-t border-stone-100">
                    <div class="hidden grid-cols-[minmax(0,1fr)_6.5rem_8.5rem_8rem_3.5rem] items-center gap-3 bg-stone-50/70 px-6 py-2.5 text-xs font-semibold tracking-wide text-stone-500 uppercase @2xl:grid">
                        <span>Metode</span><span>Persen</span><span>Biaya tetap</span><span>Ditanggung</span><span class="text-center">Aktif</span>
                    </div>
                    @foreach ($groups as $group => $items)
                        <p class="bg-stone-50 px-6 py-2 text-xs font-bold tracking-wider text-stone-500 uppercase">{{ $group }}</p>
                        <div class="divide-y divide-stone-100">
                            @foreach ($items as $method)
                                @php
                                    $key = $method->value;
                                    $rate = old("methods.{$key}") ? array_replace($methods[$key], old("methods.{$key}")) : $methods[$key];
                                    $field = "method-{$key}";
                                @endphp
                                <div class="grid grid-cols-2 items-end gap-x-3 gap-y-2 px-6 py-3 @md:grid-cols-3 @2xl:grid-cols-[minmax(0,1fr)_6.5rem_8.5rem_8rem_3.5rem] @2xl:items-center">
                                    <span class="order-1 flex min-w-0 items-center gap-2 self-center text-sm font-semibold text-stone-800 @md:col-span-2 @2xl:col-span-1"><x-icon :name="$method->icon()" class="size-4 shrink-0 text-stone-400" /> {{ $method->label() }}</span>
                                    <span class="order-2 flex items-center justify-end gap-2 self-center @2xl:order-last @2xl:justify-center">
                                        <label for="{{ $field }}-enabled" class="text-xs font-semibold text-stone-500 @2xl:sr-only">Aktif</label>
                                        <input type="hidden" name="methods[{{ $key }}][enabled]" value="0">
                                        <input type="checkbox" role="switch" id="{{ $field }}-enabled" name="methods[{{ $key }}][enabled]" value="1" class="switch" @checked($rate['enabled'])>
                                    </span>
                                    <div class="order-3 min-w-0">
                                        <label for="{{ $field }}-percent" class="mb-1 block text-xs font-semibold text-stone-500 @2xl:sr-only">Persen</label>
                                        <div class="relative">
                                            <input type="number" id="{{ $field }}-percent" name="methods[{{ $key }}][percent]" value="{{ $rate['percent'] }}" min="0" max="50" step="0.01" inputmode="decimal" class="input pr-7 tabular-nums">
                                            <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-xs text-stone-400">%</span>
                                        </div>
                                    </div>
                                    <div class="order-3 min-w-0">
                                        <label for="{{ $field }}-flat" class="mb-1 block text-xs font-semibold text-stone-500 @2xl:sr-only">Biaya tetap</label>
                                        <div class="relative">
                                            <span class="input-addon">Rp</span>
                                            <input type="text" id="{{ $field }}-flat" x-money inputmode="numeric" name="methods[{{ $key }}][flat]" value="{{ \App\Support\FormField::thousands($rate['flat']) }}" class="input pl-10 tabular-nums">
                                        </div>
                                    </div>
                                    <div class="order-3 col-span-2 min-w-0 @md:col-span-1">
                                        <label for="{{ $field }}-charge" class="mb-1 block text-xs font-semibold text-stone-500 @2xl:sr-only">Ditanggung</label>
                                        <select id="{{ $field }}-charge" name="methods[{{ $key }}][charge_to]" class="input">
                                            <option value="store" @selected($rate['charge_to'] === 'store')>Toko</option>
                                            <option value="buyer" @selected($rate['charge_to'] === 'buyer')>Pembeli</option>
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @if ($paymentDriver !== 'midtrans')
                    <p class="text-xs text-stone-400">Driver pembayaran saat ini “{{ $paymentDriver }}” (simulasi). Tarif tetap dipakai untuk perhitungan & pilihan metode.</p>
                @endif
            </section>

            {{-- Penerbit invoice --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="storefront" /></span>
                    <div class="min-w-0"><h2 class="font-bold text-stone-900">Penerbit invoice</h2><p class="text-xs text-stone-500">Identitas usaha di kepala invoice. Kosongkan untuk memakai data Pengaturan Toko.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-form.input name="company_name" label="Nama usaha / badan usaha" :value="$val('company_name')" maxlength="150" optional :placeholder="config('shop.full_name')"
                                  tooltip="Nama resmi sesuai NPWP, mis. “CV Media Gudang Acc”." />
                    <x-form.input name="company_npwp" label="NPWP" :value="$val('company_npwp')" maxlength="22" inputmode="numeric" optional placeholder="15 atau 16 digit"
                                  tooltip="NPWP usaha yang dicetak di invoice. Format 16 digit (NIK/NPWP baru) atau 15 digit lama." />
                    <x-form.textarea name="company_address" label="Alamat" :value="$val('company_address')" rows="2" maxlength="500" optional :placeholder="config('shop.address') ?: 'Alamat sesuai NPWP'" wrapper-class="sm:col-span-2" />
                    <x-form.input name="company_phone" label="Telepon" :value="$val('company_phone')" maxlength="40" optional :placeholder="$company['phone'] ?? '021-...'" />
                    <x-form.input type="email" name="company_email" label="Email" :value="$val('company_email')" maxlength="150" optional :placeholder="config('shop.email') ?: 'finance@...'" />
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ([
                        $toggle('is_pkp', 'Pengusaha Kena Pajak (PKP)', 'Tanda “PKP” tampil di samping NPWP pada invoice.'),
                        $toggle('buyer_tax_info', 'Pembeli boleh minta invoice perusahaan', 'Checkout menampilkan isian nama perusahaan & NPWP pembeli.'),
                    ] as $item)
                        <x-form.switch card :name="$item['name']" :label="$item['title']" :description="$item['text']" :checked="$item['checked']" />
                    @endforeach
                </div>
            </section>

            {{-- Nomor & isi invoice --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-sky-50 text-sky-600"><x-icon name="list-number" /></span>
                    <div class="min-w-0"><h2 class="font-bold text-stone-900">Nomor & isi invoice</h2><p class="text-xs text-stone-500">Nomor invoice berurutan tanpa celah, terpisah dari kode pesanan.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-form.input name="invoice_prefix" label="Awalan" :value="$val('invoice_prefix')" maxlength="12" optional class="uppercase"
                                  tooltip="Mengisi token {PREFIX}, mis. INV atau MGA." />
                    <x-form.input name="invoice_format" label="Format nomor" :value="$val('invoice_format')" maxlength="60" required wrapper-class="sm:col-span-2" class="font-mono"
                                  tooltip="Susunan nomor invoice dari token."
                                  :tooltip-tips="['{PREFIX} awalan, {YYYY}/{YY} tahun, {MM} bulan, {DD} tanggal, {SEQ} nomor urut, {ORDER} kode pesanan.', 'Wajib ada {SEQ} atau {ORDER} supaya nomor unik.']" />
                    <x-form.number name="invoice_padding" label="Panjang nomor urut" :value="$val('invoice_padding')" min="1" max="10" suffix="digit" required
                                   tooltip="Nomor urut diberi nol di depan, mis. 5 digit → 00042." />
                    <x-form.select name="invoice_reset" label="Nomor urut mulai dari 1 lagi" :search="false" :selected="$val('invoice_reset')"
                                   :options="['yearly' => 'Setiap tahun', 'monthly' => 'Setiap bulan', 'never' => 'Tidak pernah']"
                                   tooltip="Kapan nomor urut kembali ke 1. Pastikan format memuat tahun/bulan agar nomor tidak bentrok." />
                    <x-form.select name="invoice_issue_on" label="Invoice terbit saat" :search="false" :selected="$val('invoice_issue_on')"
                                   :options="['paid' => 'Pesanan lunas', 'created' => 'Pesanan dibuat']"
                                   tooltip="“Lunas” disarankan: pesanan yang batal sebelum dibayar tidak memakan nomor, jadi nomor invoice tetap berurutan." />
                </div>
                <div class="rounded-xl bg-stone-50 p-3 text-sm">
                    <span class="text-xs text-stone-500">Contoh nomor berikutnya</span>
                    <p class="font-mono font-semibold text-stone-900" x-text="preview?.invoice ?? @js($invoicePreview)">{{ $invoicePreview }}</p>
                </div>
                <x-form.textarea name="invoice_notes" label="Catatan di invoice" :value="$val('invoice_notes')" rows="3" maxlength="1000" optional
                                 placeholder="Mis. Barang yang sudah dibeli dapat ditukar maks. 7 hari dengan menyertakan invoice ini."
                                 tooltip="Tampil di bawah rincian barang, cocok untuk syarat garansi/retur atau info rekening." />
                <x-form.input name="invoice_footer" label="Kaki invoice" :value="$val('invoice_footer')" maxlength="300" optional />
            </section>
        </div>

        {{-- Simulasi --}}
        <div class="min-w-0 space-y-6">
            <div class="card space-y-4 p-5 lg:sticky lg:top-20">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-bold text-stone-900">Simulasi tagihan</h2>
                    <span x-show="previewLoading" x-cloak class="text-xs text-stone-400">menghitung…</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-form.money name="sample_subtotal" label="Belanja" value="1000000" />
                    <x-form.money name="sample_shipping" label="Ongkir" value="25000" />
                </div>
                <p x-show="previewError" x-cloak x-text="previewError" class="rounded-xl bg-rose-50 p-3 text-xs text-rose-700"></p>
                <template x-if="preview">
                    <div class="space-y-4">
                        <dl class="space-y-1.5 text-sm">
                            <template x-for="row in preview.rows" :key="row.label">
                                <div class="flex justify-between gap-3" :class="row.info ? 'text-xs text-stone-500' : ''">
                                    <dt :class="row.info ? '' : 'text-stone-500'" x-text="row.label"></dt>
                                    <dd class="tabular-nums" x-text="(row.negative ? '−' : '') + row.amount"></dd>
                                </div>
                            </template>
                            <div class="flex justify-between border-t border-stone-200 pt-2 font-bold text-stone-900"><dt>Total pesanan</dt><dd class="tabular-nums" x-text="preview.total"></dd></div>
                            <p x-show="preview.dpp" class="text-xs text-stone-500">DPP: <span x-text="preview.dpp"></span></p>
                            <p x-show="preview.priceNote" class="text-xs text-stone-500" x-text="preview.priceNote"></p>
                        </dl>
                        <div>
                            <p class="mb-1.5 text-xs font-bold tracking-wider text-stone-500 uppercase">Per metode bayar</p>
                            <ul class="divide-y divide-stone-100 text-xs">
                                <template x-for="m in preview.methods" :key="m.label">
                                    <li class="flex items-start justify-between gap-3 py-1.5">
                                        <span class="min-w-0 font-medium text-stone-700" x-text="m.label"></span>
                                        <span class="shrink-0 text-right tabular-nums">
                                            <span class="block font-semibold text-stone-900" x-text="m.net"></span>
                                            <span class="block text-[11px] text-stone-500"><span x-show="m.buyer !== '—'">pembeli +<span x-text="m.buyer"></span> · </span>Midtrans <span class="text-rose-600">−<span x-text="m.gateway"></span></span></span>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                            <p class="mt-1.5 text-[11px] text-stone-400">Angka tebal = dana bersih yang diterima toko.</p>
                            <p x-show="preview.choice" class="mt-2 text-xs text-stone-500">Ada biaya untuk pembeli: pembeli memilih metode di halaman bayar sebelum diarahkan ke Midtrans.</p>
                        </div>
                    </div>
                </template>
                <x-admin.save-bar>
                    <button class="btn btn-primary w-full">Simpan pengaturan</button>
                </x-admin.save-bar>
            </div>
        </div>
    </form>
</x-layouts.admin>
