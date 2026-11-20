@php
    $val = fn (string $field) => old($field, $values[$field] ?? null);
    // Nilai bawaan .env sebagai petunjuk (hanya teks biasa).
    $envHint = fn (string $field) => filled($defaults[$field] ?? null) && ! is_array($defaults[$field]) && ! is_bool($defaults[$field])
        ? 'Kosongkan untuk memakai nilai .env: '.$defaults[$field]
        : null;
    $selectedCouriers = old('couriers', $values['couriers'] ?? []);
@endphp
<x-layouts.admin title="Pengaturan Toko">
    <x-admin.page-header title="Pengaturan Toko" description="Identitas, kontak, pengiriman, dan pembayaran. Perubahan langsung berlaku di seluruh toko." />

    <form method="POST" action="{{ route('admin.store-settings.update') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="space-y-6 lg:col-span-2">
            {{-- Identitas --}}
            <section id="identitas-toko" class="card scroll-mt-24 space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-stone-100 text-stone-500"><x-icon name="storefront" /></span>
                    <div><h2 class="font-bold text-stone-900">Identitas toko</h2><p class="text-xs text-stone-500">Tampil di judul halaman, email, invoice, dan footer.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-form.input name="name" label="Nama singkat" :value="$values['name']" required maxlength="30"
                                  tooltip="Dipakai di tempat sempit: judul tab browser dan beberapa email. Mis. MGA." />
                    <x-form.input name="full_name" label="Nama lengkap" :value="$values['full_name']" required maxlength="80"
                                  tooltip="Nama resmi toko di footer, invoice, packing slip, dan saat link toko dibagikan. Mis. Media Gudang Acc." />
                </div>
                <x-form.input name="tagline" label="Tagline" :value="$values['tagline']" maxlength="160" optional
                              tooltip="Kalimat pendek tentang toko yang tampil di footer semua halaman."
                              tooltip-tips="Tulis tanpa titik di akhir (titik ditambahkan otomatis), mis. “Grosir aksesoris HP original”." />
            </section>

            {{-- Kontak & sosial --}}
            <section id="kontak-toko" class="card scroll-mt-24 space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-stone-100 text-stone-500"><x-icon name="chat" /></span>
                    <div><h2 class="font-bold text-stone-900">Kontak & media sosial</h2><p class="text-xs text-stone-500">Dipakai untuk tombol WhatsApp dan tautan profil toko.</p></div>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-form.phone name="whatsapp" label="No. WhatsApp" :value="$values['whatsapp']" placeholder="0812-3456-7890" hint="Disimpan dalam format 62xxx." optional
                                  tooltip="Nomor tujuan tombol WhatsApp melayang, tombol “Hubungi kami”, dan kontak di footer & packing slip."
                                  tooltip-tips="Pakai nomor WhatsApp (Business) yang selalu aktif dan dipegang admin/CS." />
                    <x-form.input type="email" name="email" label="Email kontak" icon="envelope" :value="$values['email']" placeholder="halo@tokoanda.com" optional />
                    <x-form.input name="instagram" label="Instagram" prefix="@" :value="$values['instagram']" placeholder="username" optional />
                    <x-form.input name="tiktok" label="TikTok" prefix="@" :value="$values['tiktok']" placeholder="username" optional />
                    <x-form.input type="url" name="facebook" label="Halaman Facebook" icon="external" :value="$values['facebook']" placeholder="https://facebook.com/namatoko" optional wrapper-class="sm:col-span-2" />
                </div>
                <x-form.switch card name="whatsapp_button" label="Tombol WhatsApp melayang" :checked="$values['whatsapp_button']"
                               description="Tombol chat di pojok kanan bawah halaman toko, langsung ke nomor WhatsApp di atas. Tidak tampil bila nomor kosong." />
                <x-form.textarea name="whatsapp_message" label="Pesan pembuka chat" :value="$values['whatsapp_message']" rows="2" maxlength="300" optional
                                 placeholder="Halo, saya mau tanya tentang produk di toko online."
                                 hint="Otomatis terisi saat pelanggan membuka chat. Di halaman produk, nama & link produk ikut ditambahkan."
                                 tooltip="Teks yang sudah terketik di WhatsApp pelanggan saat menekan tombol chat; pelanggan tinggal kirim."
                                 tooltip-tips="Singkat & sopan, mis. “Halo MGA, saya mau tanya stok.” Admin langsung tahu chat berasal dari toko online." />
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
                         postalCode: @js($val('origin_postal_code')),
                     })">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-stone-100 text-stone-500"><x-icon name="truck" /></span>
                    <div><h2 class="font-bold text-stone-900">Pengiriman</h2><p class="text-xs text-stone-500">Asal pengiriman dipakai untuk menghitung ongkir di checkout. Penyedia ongkir aktif: <span class="font-semibold text-stone-700">{{ $shippingProvider }}</span>.</p></div>
                </div>

                @if ($shippingKeyMissing)
                    <p class="flex gap-2 rounded-xl bg-rose-50 p-3 text-xs text-rose-700"><x-icon name="warning" class="size-4 shrink-0" /> API key {{ $shippingProvider }} belum diisi di Admin › Integrasi, jadi ongkir belum bisa dihitung.</p>
                @elseif ($shippingDriver !== 'fake' && blank($values['district_id']))
                    <p class="flex gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"><x-icon name="warning" class="size-4 shrink-0" /> Kecamatan asal untuk {{ $shippingProvider }} belum dipilih. Pilih di bawah lalu simpan agar ongkir bisa dihitung. RajaOngkir dan Biteship memakai data wilayah berbeda, jadi asal dan kurir disimpan terpisah per penyedia.</p>
                @endif

                <div class="flex items-start gap-3 border-t border-stone-100 pt-5">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-stone-900 text-xs font-bold text-white">1</span>
                    <div><h3 class="text-sm font-bold text-stone-900">Lokasi gudang</h3><p class="text-xs text-stone-500">Alamat pengirim dan asal pengiriman untuk menghitung ongkir.</p></div>
                </div>
                <x-form.textarea name="address" label="Alamat lengkap gudang" :value="$values['address']" rows="3" maxlength="500" optional
                                 placeholder="Jl. ..., Kecamatan, Kota, Kode pos"
                                 :hint="trim('Dicetak di invoice, packing slip, dan label resi. Kurir menjemput paket di alamat ini. '.$envHint('address'))" />
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    @foreach ([
                        ['province', 'province_id', 'Provinsi'],
                        ['city', 'city_id', 'Kota / Kabupaten'],
                        ['district', 'district_id', 'Kecamatan'],
                    ] as [$ref, $field, $text])
                        <x-form.field :label="$text" :for="$field" :name="$field"
                                      :tooltip="$ref === 'district' ? 'Kecamatan gudang/lokasi pengiriman. Ongkir di checkout dihitung dari kecamatan ini ke alamat pelanggan.' : null"
                                      :tooltip-tips="$ref === 'district' ? 'Wajib diisi sebelum memakai RajaOngkir atau Biteship; salah kecamatan = ongkir yang ditampilkan salah.' : null">
                            <select id="{{ $field }}" name="{{ $field }}" x-ref="{{ $ref }}" @class(['input', 'input-error' => $errors->has($field)])></select>
                        </x-form.field>
                    @endforeach
                </div>
                <p x-show="error" x-cloak x-text="error" class="flex gap-2 rounded-xl bg-rose-50 p-3 text-xs text-rose-700"></p>
                <p class="hint">Kecamatan asal saat ini: <span class="font-semibold text-stone-700">{{ $values['origin_label'] ?: ($values['district_id'] ? 'ID '.$values['district_id'] : 'belum diatur') }}</span>. Biarkan seperti semula bila tidak berubah.</p>
                @if ($shippingDriver === 'biteship')
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <x-form.input name="origin_postal_code" label="Kode pos gudang" optional x-model="postalCode" inputmode="numeric" maxlength="5" placeholder="5 digit"
                                      tooltip="Biteship menghitung ongkir dari kode pos gudang ke kode pos tujuan. Terisi otomatis saat kecamatan dipilih."
                                      tooltip-tips="Ganti dengan kode pos gudang yang sebenarnya supaya tarif lebih tepat. Kosong = kode pos umum kecamatan asal." />
                    </div>
                    <x-form.map-pin label="Pin lokasi gudang" lat-name="origin_latitude" lng-name="origin_longitude"
                                    :latitude="$instant['latitude']" :longitude="$instant['longitude']"
                                    tooltip="Titik tepat gudang/lokasi penjemputan. Kurir instan menghitung ongkir dan menjemput paket dari titik ini."
                                    tooltip-tips="Perbesar peta sampai bangunan gudang terlihat, lalu ketuk tepat di pintu masuknya."
                                    hint="Dipakai kurir instan untuk menghitung ongkir dan menjemput paket. Wajib bila ada kurir instan yang dicentang." />
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl bg-stone-50 p-3 text-xs text-stone-600">
                        <span>Kontak pengirim untuk kurir:</span>
                        @if (filled($values['whatsapp']))
                            <span class="font-semibold text-stone-800">{{ $values['whatsapp'] }}</span>
                        @else
                            <span class="badge bg-amber-50 text-amber-700 ring-amber-600/20">Belum diisi</span>
                        @endif
                        <a href="#kontak-toko" class="font-semibold text-brand-600 hover:underline">{{ filled($values['whatsapp']) ? 'Ubah' : 'Isi' }} No. WhatsApp di kartu Kontak</a>
                    </p>
                    @if (blank($values['address']) || blank($values['whatsapp']))
                        <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Alamat lengkap gudang dan nomor WhatsApp wajib terisi sebelum pengiriman Biteship bisa dibuat.</p>
                    @endif
                @endif

                <div class="flex items-start gap-3 border-t border-stone-100 pt-5">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-stone-900 text-xs font-bold text-white">2</span>
                    <div><h3 class="text-sm font-bold text-stone-900">Kurir reguler</h3><p class="text-xs text-stone-500">Kurir yang muncul sebagai pilihan ongkir di checkout.</p></div>
                </div>
                <div x-data="courierRecommendation({ url: @js(route('admin.store-settings.couriers')) })">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="label-row">
                            <p class="label">Kurir yang ditawarkan di checkout</p>
                            <x-tooltip label="Kurir" text="Hanya kurir yang dicentang yang muncul sebagai pilihan ongkir pelanggan. Daftar ini = kurir yang didukung {{ $shippingProvider === 'Biteship' ? 'Biteship (kurir instan seperti Gojek/Grab tidak termasuk)' : 'RajaOngkir' }}."
                                       :tips="['Klik “Cek rekomendasi” untuk melihat kurir yang benar-benar punya tarif dari kecamatan asal (ke sekitar gudang & ke kota besar di provinsi lain).', 'Pilih kurir yang melayani pickup/drop-off di dekat gudang. Terlalu banyak pilihan membuat checkout lebih lambat.']" />
                        </div>
                        @if ($canRecommend)
                            <button type="button" class="btn btn-secondary btn-sm" x-on:click="check()" x-bind:disabled="loading">
                                <span x-show="!loading"><x-icon name="sparkles" class="size-4" /></span>
                                <span x-show="loading" x-cloak class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                                <span x-text="loading ? 'Mengecek tarif…' : 'Cek rekomendasi RajaOngkir'">Cek rekomendasi RajaOngkir</span>
                            </button>
                        @endif
                    </div>

                    <div x-show="result && result.recommended.length" x-cloak class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-brand-50 p-3 text-xs text-brand-800">
                        <p>
                            <span class="font-semibold" x-text="result ? result.recommended.length + ' kurir direkomendasikan' : ''"></span>
                            <span x-text="result ? '— punya tarif dari kecamatan asal ke ' + result.destinations.join(' & ') + '. Dicek ' + checkedAt() + '.' : ''"></span>
                        </p>
                        <span class="flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm" x-on:click="applyRecommended()">Pilih yang direkomendasikan</button>
                            <button type="button" class="btn btn-ghost btn-sm" x-on:click="check(true)" x-bind:disabled="loading">Cek ulang</button>
                        </span>
                    </div>
                    <p x-show="message" x-cloak x-text="message" class="mt-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"></p>

                    <div class="mt-2 grid grid-cols-1 gap-2 min-[380px]:grid-cols-2 sm:grid-cols-3">
                        @foreach ($couriers as $code => $label)
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-stone-200 px-3 py-2.5 text-sm transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50"
                                   x-bind:class="info(@js($code)) && !info(@js($code)).available && 'opacity-60'">
                                <input type="checkbox" name="couriers[]" value="{{ $code }}" class="checkbox mt-0.5" @checked(in_array($code, $selectedCouriers, true))>
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-center gap-1.5 font-semibold text-stone-700">
                                        {{ $label }}
                                        <span x-show="isRecommended(@js($code))" x-cloak class="badge bg-accent-100 text-accent-700">Rekomendasi</span>
                                    </span>
                                    <span class="block text-[11px] text-stone-400" x-show="!info(@js($code))">{{ in_array($code, $trackableCouriers, true) ? 'Ongkir + lacak resi' : 'Ongkir saja (resi tidak bisa dilacak otomatis)' }}</span>
                                    <span class="block text-[11px] text-stone-500" x-show="info(@js($code))" x-cloak x-text="summary(@js($code))"></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if ($shippingDriver === 'fake')
                        <p class="mt-1.5 text-xs text-stone-400">Mode simulasi hanya punya tarif contoh JNE, SiCepat, J&T, POS, dan TIKI. Kurir lain aktif setelah RajaOngkir atau Biteship dipilih di Admin › Integrasi.</p>
                    @elseif ($shippingDriver === 'biteship')
                        <p class="mt-1.5 text-xs text-stone-400">Kurir yang dicentang juga harus aktif di akun Biteship (dashboard Biteship › Pengaturan kurir). Rekomendasi kurir otomatis hanya tersedia untuk RajaOngkir.</p>
                    @elseif (! $canRecommend)
                        <p class="mt-1.5 text-xs text-stone-400">Isi API key RajaOngkir di Admin › Integrasi untuk melihat rekomendasi kurir.</p>
                    @endif
                    <x-input-error for="couriers" />
                </div>

                @if ($shippingDriver === 'biteship')
                    {{-- Kurir instan: tarif dari koordinat gudang ke pin lokasi alamat pembeli. --}}
                <div class="flex items-start gap-3 border-t border-stone-100 pt-5">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-stone-900 text-xs font-bold text-white">3</span>
                    <div><h3 class="text-sm font-bold text-stone-900">Kurir instan</h3><p class="text-xs text-stone-500">Antar di hari yang sama dalam kota. Butuh pin lokasi gudang di bagian 1.</p></div>
                </div>
                    <div class="space-y-4">
                        <input type="hidden" name="instant_submitted" value="1">
                        <div>
                            <div class="label-row">
                                <p class="label">Kurir instan yang ditawarkan</p>
                                <x-tooltip label="Kurir instan" text="Kurir yang mengantar di hari yang sama dalam kota (Lalamove, Gojek, Grab). Tarifnya dihitung dari titik gudang ke pin lokasi pembeli, bukan dari kode pos."
                                           :tips="['Hanya muncul di checkout untuk pembeli yang menaruh pin lokasi di alamatnya dan berjarak maksimal '.config('shop.shipping.biteship.instant_max_km', 40).' km dari gudang.', 'Toko hanya menampilkan tarifnya. Driver tetap dipesan sendiri lewat aplikasi kurir atau dashboard Biteship setelah pesanan dibayar.', 'Kurir yang dicentang harus aktif di akun Biteship.']" />
                            </div>
                            @php($selectedInstant = old('instant_couriers', $instant['couriers']))
                            <div class="grid grid-cols-1 gap-2 min-[380px]:grid-cols-2 sm:grid-cols-3">
                                @foreach ($instantCouriers as $code => $label)
                                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-stone-200 px-3 py-2.5 text-sm transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                        <input type="checkbox" name="instant_couriers[]" value="{{ $code }}" class="checkbox mt-0.5" @checked(in_array($code, $selectedInstant, true))>
                                        <span class="min-w-0"><span class="block font-semibold text-stone-700">{{ $label }}</span><span class="block text-[11px] text-stone-400">Butuh pin lokasi pembeli</span></span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error for="instant_couriers" />
                        </div>
                    </div>
                @endif

                <div class="flex items-start gap-3 border-t border-stone-100 pt-5">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-stone-900 text-xs font-bold text-white">{{ $shippingDriver === 'biteship' ? 4 : 3 }}</span>
                    <div><h3 class="text-sm font-bold text-stone-900">Pelacakan</h3><p class="text-xs text-stone-500">Status resi setelah paket dikirim.</p></div>
                </div>
                <x-form.switch card name="tracking_sync" label="Cek resi otomatis" :checked="$values['tracking_sync']"
                               description="Setiap 3 jam status resi pesanan “Dikirim” dicek ke kurir; yang sudah sampai ditandai otomatis. Butuh cron scheduler aktif." />
            </section>
        </div>

        <div class="space-y-6">
            {{-- Pesanan & pembayaran --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-stone-100 text-stone-500"><x-icon name="clock" /></span>
                    <div><h2 class="font-bold text-stone-900">Pesanan & pembayaran</h2></div>
                </div>
                <x-form.number name="payment_expiry_hours" label="Batas waktu bayar" :value="$values['payment_expiry_hours']" min="1" max="168" required suffix="jam"
                               hint="Lewat dari ini pesanan dibatalkan otomatis dan stok kembali. Berlaku untuk pesanan baru."
                               tooltip="Waktu yang diberikan ke pelanggan untuk membayar setelah membuat pesanan. Selama menunggu, stok barang ditahan untuk pesanan itu."
                               tooltip-tips="24 jam umum dipakai. Terlalu lama membuat stok tertahan oleh pesanan yang tidak jadi dibayar." />
                <x-form.number name="auto_complete_days" label="Selesai otomatis setelah sampai" :value="$values['auto_complete_days']" min="1" max="30" required suffix="hari"
                               hint='Bila pelanggan tidak menekan "Pesanan diterima".'
                               tooltip="Pesanan berstatus “Sampai Tujuan” otomatis menjadi Selesai setelah sekian hari. Pesanan Selesai tidak bisa diubah lagi." />
            </section>

            {{-- Grosir --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-stone-100 text-stone-500"><x-icon name="tag" /></span>
                    <div><h2 class="font-bold text-stone-900">Grosir</h2><p class="text-xs text-stone-500">Harga grosir diatur per produk di form produk.</p></div>
                </div>
                <x-form.switch card name="wholesale_voucher" label="Voucher berlaku untuk harga grosir" :checked="$values['wholesale_voucher']"
                               description="Matikan bila voucher hanya untuk barang berharga normal."
                               tooltip="Aktif: potongan voucher dihitung dari semua barang. Nonaktif: barang yang sudah dapat harga grosir tidak ikut dihitung (termasuk syarat minimal belanja), dan voucher ditolak bila semua barang berharga grosir."
                               :tooltip-tips="['Matikan supaya margin tidak tergerus dua kali (harga grosir + voucher).']" />
            </section>

            <div class="flex gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                <x-icon name="info" class="size-5 shrink-0" />
                @can('integrations.manage')
                    <p>Driver & kunci API Midtrans, ekspedisi (RajaOngkir/Biteship), dan Firebase diatur di <a href="{{ route('admin.integrations.index') }}" class="font-semibold underline">Integrasi</a>.</p>
                @else
                    <p>Driver & kunci API Midtrans, ekspedisi (RajaOngkir/Biteship), dan Firebase hanya bisa diatur oleh Superadmin.</p>
                @endcan
            </div>

            <x-admin.save-bar class="lg:sticky lg:top-20 lg:rounded-2xl lg:border lg:border-stone-200/70 lg:bg-white lg:p-4 lg:shadow-xs">
                <button class="btn btn-primary w-full">Simpan pengaturan</button>
            </x-admin.save-bar>
        </div>
    </form>
</x-layouts.admin>
