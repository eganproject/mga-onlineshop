@php
    $val = fn (string $field) => old($field, $values[$field] ?? null);
    $envHint = fn (string $field) => filled($defaults[$field] ?? null) && ! is_array($defaults[$field]) && ! is_bool($defaults[$field])
        ? 'Kosongkan untuk memakai nilai .env: '.$defaults[$field]
        : null;
    $selectedCouriers = old('couriers', $values['couriers'] ?? []);
@endphp
<x-layouts.admin title="Pengaturan Toko">
    <x-admin.page-header title="Pengaturan Toko" description="Identitas, kontak, pengiriman, dan pembayaran. Perubahan langsung berlaku di seluruh toko." />

    <form method="POST" action="{{ route('admin.store-settings.update') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="space-y-6 lg:col-span-2">
            {{-- Identitas --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="storefront" /></span>
                    <div><h2 class="font-bold text-stone-900">Identitas toko</h2><p class="text-xs text-stone-500">Tampil di judul halaman, email, invoice, dan footer.</p></div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="label">Nama singkat</label>
                        <input id="name" name="name" value="{{ $val('name') }}" required maxlength="30" class="input @error('name') input-error @enderror">
                        <x-input-error for="name" />
                    </div>
                    <div>
                        <label for="full_name" class="label">Nama lengkap</label>
                        <input id="full_name" name="full_name" value="{{ $val('full_name') }}" required maxlength="80" class="input @error('full_name') input-error @enderror">
                        <x-input-error for="full_name" />
                    </div>
                </div>
                <div>
                    <label for="tagline" class="label">Tagline</label>
                    <input id="tagline" name="tagline" value="{{ $val('tagline') }}" maxlength="160" class="input @error('tagline') input-error @enderror">
                    <x-input-error for="tagline" />
                </div>
                <div>
                    <label for="address" class="label">Alamat gudang / pengirim</label>
                    <textarea id="address" name="address" rows="3" maxlength="500" class="input @error('address') input-error @enderror" placeholder="Jl. ..., Kecamatan, Kota, Kode pos">{{ $val('address') }}</textarea>
                    <p class="mt-1.5 text-xs text-stone-400">Dicetak di invoice dan packing slip. {{ $envHint('address') }}</p>
                    <x-input-error for="address" />
                </div>
            </section>

            {{-- Kontak & sosial --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="chat" /></span>
                    <div><h2 class="font-bold text-stone-900">Kontak & media sosial</h2><p class="text-xs text-stone-500">Dipakai untuk tombol WhatsApp dan tautan profil toko.</p></div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="whatsapp" class="label">No. WhatsApp</label>
                        <input id="whatsapp" name="whatsapp" type="tel" value="{{ $val('whatsapp') }}" placeholder="081234567890" class="input @error('whatsapp') input-error @enderror">
                        <p class="mt-1.5 text-xs text-stone-400">Disimpan dalam format 62xxx.</p>
                        <x-input-error for="whatsapp" />
                    </div>
                    <div>
                        <label for="email" class="label">Email kontak</label>
                        <input id="email" name="email" type="email" value="{{ $val('email') }}" placeholder="halo@tokoanda.com" class="input @error('email') input-error @enderror">
                        <x-input-error for="email" />
                    </div>
                    <div>
                        <label for="instagram" class="label">Instagram</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-stone-400">@</span>
                            <input id="instagram" name="instagram" value="{{ $val('instagram') }}" placeholder="username" class="input pl-8 @error('instagram') input-error @enderror">
                        </div>
                        <x-input-error for="instagram" />
                    </div>
                    <div>
                        <label for="tiktok" class="label">TikTok</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-stone-400">@</span>
                            <input id="tiktok" name="tiktok" value="{{ $val('tiktok') }}" placeholder="username" class="input pl-8 @error('tiktok') input-error @enderror">
                        </div>
                        <x-input-error for="tiktok" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="facebook" class="label">Halaman Facebook</label>
                        <input id="facebook" name="facebook" type="url" value="{{ $val('facebook') }}" placeholder="https://facebook.com/namatoko" class="input @error('facebook') input-error @enderror">
                        <x-input-error for="facebook" />
                    </div>
                </div>
            </section>

            {{-- Pengiriman --}}
            <section class="card space-y-5 p-6"
                     x-data="regionPicker({
                         urls: {
                             provinces: @js(route('admin.regions.provinces')),
                             cities: @js(route('admin.regions.cities', '__ID__')),
                             districts: @js(route('admin.regions.districts', '__ID__')),
                         },
                         provinceId: @js($val('province_id')),
                         cityId: @js($val('city_id')),
                         districtId: @js($val('district_id')),
                     })">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-sky-50 text-sky-600"><x-icon name="truck" /></span>
                    <div><h2 class="font-bold text-stone-900">Pengiriman</h2><p class="text-xs text-stone-500">Asal pengiriman dipakai untuk menghitung ongkir di checkout.</p></div>
                </div>

                <div class="rounded-xl bg-stone-50 p-3 text-sm">
                    <p class="text-xs text-stone-500">Kecamatan asal saat ini</p>
                    <p class="font-semibold text-stone-800">{{ $values['origin_label'] ?: ($values['district_id'] ? 'ID '.$values['district_id'] : 'Belum diatur') }}</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="province_id" class="label">Provinsi</label>
                        <select id="province_id" name="province_id" x-model="provinceId" @change="changeProvince()" :disabled="loading.provinces" class="input @error('province_id') input-error @enderror">
                            <option value="" x-text="loading.provinces ? 'Memuat...' : 'Pilih provinsi'">Pilih provinsi</option>
                            <template x-for="item in provinces" :key="item.id">
                                <option :value="item.id" x-text="item.name" :selected="item.id === provinceId"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label for="city_id" class="label">Kota / Kabupaten</label>
                        <select id="city_id" name="city_id" x-model="cityId" @change="changeCity()" :disabled="!provinceId || loading.cities" class="input disabled:bg-stone-50 disabled:text-stone-400">
                            <option value="" x-text="loading.cities ? 'Memuat...' : 'Pilih kota'">Pilih kota</option>
                            <template x-for="item in cities" :key="item.id">
                                <option :value="item.id" x-text="item.name" :selected="item.id === cityId"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label for="district_id" class="label">Kecamatan</label>
                        <select id="district_id" name="district_id" x-model="districtId" :disabled="!cityId || loading.districts" class="input disabled:bg-stone-50 disabled:text-stone-400 @error('district_id') input-error @enderror">
                            <option value="" x-text="loading.districts ? 'Memuat...' : 'Pilih kecamatan'">Pilih kecamatan</option>
                            <template x-for="item in districts" :key="item.id">
                                <option :value="item.id" x-text="item.name" :selected="item.id === districtId"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <p x-show="error" x-cloak x-text="error" class="text-xs text-rose-600"></p>
                <x-input-error for="district_id" />

                <div>
                    <p class="label">Kurir yang ditawarkan di checkout</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($couriers as $code => $label)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-stone-200 px-3 py-2.5 text-sm font-semibold text-stone-700 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input type="checkbox" name="couriers[]" value="{{ $code }}" class="checkbox" @checked(in_array($code, $selectedCouriers, true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @if ($shippingDriver === 'fake')
                        <p class="mt-1.5 text-xs text-stone-400">Mode simulasi hanya punya tarif contoh JNE, SiCepat, J&T, POS, dan TIKI.</p>
                    @endif
                    <x-input-error for="couriers" />
                </div>

                <label class="flex items-start justify-between gap-4 rounded-xl border border-stone-200 p-4">
                    <span>
                        <span class="block text-sm font-semibold text-stone-800">Cek resi otomatis</span>
                        <span class="text-xs text-stone-500">Setiap 3 jam status resi pesanan "Dikirim" dicek ke kurir; yang sudah sampai ditandai otomatis. Butuh cron scheduler aktif.</span>
                    </span>
                    <input type="hidden" name="tracking_sync" value="0">
                    <input type="checkbox" name="tracking_sync" value="1" class="checkbox mt-0.5 size-5 shrink-0" @checked(old('tracking_sync', $values['tracking_sync']))>
                </label>
            </section>
        </div>

        <div class="space-y-6">
            {{-- Pesanan & pembayaran --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><x-icon name="clock" /></span>
                    <div><h2 class="font-bold text-stone-900">Pesanan & pembayaran</h2></div>
                </div>
                <div>
                    <label for="payment_expiry_hours" class="label">Batas waktu bayar</label>
                    <div class="flex items-center gap-2">
                        <input id="payment_expiry_hours" name="payment_expiry_hours" type="number" min="1" max="168" required value="{{ $val('payment_expiry_hours') }}" class="input @error('payment_expiry_hours') input-error @enderror">
                        <span class="text-sm text-stone-500">jam</span>
                    </div>
                    <p class="mt-1.5 text-xs text-stone-400">Lewat dari ini pesanan dibatalkan otomatis dan stok kembali. Berlaku untuk pesanan baru.</p>
                    <x-input-error for="payment_expiry_hours" />
                </div>
                <div>
                    <label for="auto_complete_days" class="label">Selesai otomatis setelah sampai</label>
                    <div class="flex items-center gap-2">
                        <input id="auto_complete_days" name="auto_complete_days" type="number" min="1" max="30" required value="{{ $val('auto_complete_days') }}" class="input @error('auto_complete_days') input-error @enderror">
                        <span class="text-sm text-stone-500">hari</span>
                    </div>
                    <p class="mt-1.5 text-xs text-stone-400">Bila pelanggan tidak menekan "Pesanan diterima".</p>
                    <x-input-error for="auto_complete_days" />
                </div>
            </section>

            <div class="flex gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                <x-icon name="info" class="size-5 shrink-0" />
                <p>Kunci API Midtrans & RajaOngkir tetap di file <code class="font-mono">.env</code> demi keamanan. Statusnya bisa dilihat di <a href="{{ route('admin.settings') }}" class="font-semibold underline">Integrasi</a>.</p>
            </div>

            <div class="card sticky top-20 p-4">
                <button class="btn btn-primary w-full">Simpan pengaturan</button>
            </div>
        </div>
    </form>
</x-layouts.admin>
