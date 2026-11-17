<x-layouts.admin title="Penjualan manual">
    <x-admin.page-header title="Penjualan manual" description="Catat pesanan dari WhatsApp, toko, atau pameran. Setelah disimpan, buat tagihan QRIS / VA untuk pembeli." :back="route('admin.orders.index')" />

    <form method="POST" action="{{ route('admin.orders.store') }}"
          x-data="manualOrder({
              urls: {
                  products: @js(route('admin.orders.manual.products')),
                  rates: @js(route('admin.orders.manual.rates')),
                  quote: @js(route('admin.orders.manual.quote')),
              },
              old: @js(session()->getOldInput()),
          })"
          class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        @csrf
        <x-form.errors class="lg:col-span-3" />

        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Produk --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-center gap-1.5">
                    <h2 class="font-bold text-stone-900">Produk</h2>
                    <x-tooltip label="Produk" text="Cari nama atau SKU. Harga grosir otomatis dipakai sesuai jumlah, dan stok langsung dipotong saat pesanan disimpan."
                               :tips="['Stok kembali otomatis bila pesanan dibatalkan atau tidak dibayar sampai batas waktu.']" />
                </div>

                <div class="relative mt-4" x-on:click.outside="searchOpen = false">
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" x-ref="search" x-model="query" x-on:input="search()" x-on:focus="query && (searchOpen = true)"
                           x-on:keydown.escape="searchOpen = false" x-on:keydown.enter.prevent="results[0] && add(results[0])"
                           class="input pl-10" placeholder="Cari produk (nama atau SKU)..." aria-label="Cari produk" autocomplete="off">
                    <div x-show="searchOpen" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 max-h-80 overflow-y-auto rounded-xl border border-stone-200 bg-white shadow-lg">
                        <p x-show="searching" class="p-3 text-sm text-stone-500">Mencari…</p>
                        <p x-show="!searching && !results.length" class="p-3 text-sm text-stone-500">Produk tidak ditemukan.</p>
                        <template x-for="product in results" x-bind:key="product.id">
                            <button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-stone-50" x-on:click="add(product)">
                                <img x-show="product.image" x-bind:src="product.image" alt="" class="size-10 shrink-0 rounded-lg object-cover">
                                <span x-show="!product.image" class="grid size-10 shrink-0 place-items-center rounded-lg bg-stone-100 text-stone-400"><x-icon name="cube" class="size-5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="line-clamp-2 font-semibold text-stone-800" x-text="product.name"></span>
                                    <span class="block text-xs text-stone-500">
                                        <span x-text="product.has_variants ? product.variants.length + ' varian' : product.price_label"></span>
                                        · stok <span x-text="product.stock"></span>
                                        <span x-show="!product.active" class="text-amber-700">· nonaktif di toko</span>
                                        <span x-show="product.wholesale" class="text-accent-700">· grosir</span>
                                    </span>
                                </span>
                                <x-icon name="plus" class="size-4 shrink-0 text-brand-600" />
                            </button>
                        </template>
                    </div>
                </div>

                <p x-show="!lines.length" class="mt-4 rounded-xl border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">Belum ada produk. Cari lalu pilih produk di atas.</p>

                <ul class="mt-4 divide-y divide-stone-100" x-show="lines.length" x-cloak>
                    <template x-for="(line, index) in lines" x-bind:key="line.key">
                        <li class="py-3">
                            <input type="hidden" x-bind:name="`items[${index}][product_id]`" x-bind:value="line.product.id">
                            <input type="hidden" x-bind:name="`items[${index}][variant_id]`" x-bind:value="line.variantId">
                            <div class="flex items-start gap-3">
                                <img x-show="line.product.image" x-bind:src="line.product.image" alt="" class="size-12 shrink-0 rounded-lg object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="line-clamp-2 text-sm font-semibold text-stone-900" x-text="line.product.name"></p>
                                    <template x-if="line.product.has_variants">
                                        <select x-model="line.variantId" class="input mt-2 py-1.5 text-sm" aria-label="Varian">
                                            <option value="">Pilih varian</option>
                                            <template x-for="variant in line.product.variants" x-bind:key="variant.id">
                                                <option x-bind:value="String(variant.id)" x-bind:selected="String(variant.id) === line.variantId"
                                                        x-text="`${variant.name} · ${variant.price_label} · stok ${variant.stock}${variant.active ? '' : ' (nonaktif)'}`"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <p class="mt-1 text-xs text-stone-500">
                                        Stok <span x-text="stockOf(line)"></span>
                                        <template x-if="linePrice(index)">
                                            <span>· <span x-text="rupiah(linePrice(index).price)"></span>/pcs
                                                <span x-show="linePrice(index).regular_price" class="font-semibold text-accent-700">grosir</span>
                                            </span>
                                        </template>
                                    </p>
                                    <p x-show="problem(index)" x-text="problem(index)" class="mt-1 text-xs font-semibold text-rose-600"></p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <div class="flex items-center gap-1">
                                        <input type="number" min="1" x-model.number="line.quantity" x-bind:name="`items[${index}][quantity]`"
                                               class="input w-20 py-1.5 text-center text-sm" aria-label="Jumlah">
                                        <button type="button" class="btn btn-ghost btn-sm p-2 text-stone-400 hover:text-rose-600" x-on:click="remove(index)" title="Hapus"><x-icon name="trash" class="size-4" /><span class="sr-only">Hapus</span></button>
                                    </div>
                                    <p class="text-sm font-semibold text-stone-900 tabular-nums" x-text="linePrice(index) ? rupiah(linePrice(index).subtotal) : '—'"></p>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>
                <x-input-error for="items" />
            </section>

            {{-- Pembeli --}}
            <section class="card space-y-4 p-5 sm:p-6">
                <h2 class="font-bold text-stone-900">Pembeli</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-form.input name="customer_name" label="Nama pembeli / penerima" required maxlength="255" autocomplete="off" />
                    <x-form.phone name="customer_phone" label="No. HP / WhatsApp" required />
                    <x-form.input name="customer_email" type="email" label="Email" optional x-model.lazy="customerEmail" autocomplete="off"
                                  tooltip="Bila sama dengan email akun pelanggan, pesanan ikut tampil di akunnya dan pelanggan bisa bayar dari sana. Kosongkan untuk pembeli tanpa akun." />
                    <div class="flex items-end">
                        <p x-show="quote?.customer" x-cloak class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800"><x-icon name="check-circle" class="size-4 shrink-0" /> <span>Terhubung ke akun <b x-text="quote?.customer?.name"></b></span></p>
                    </div>
                </div>
            </section>

            {{-- Pengiriman --}}
            <section class="card space-y-4 p-5 sm:p-6">
                <h2 class="font-bold text-stone-900">Pengiriman</h2>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach (['courier' => ['truck', 'Cek ongkir kurir', 'Tarif RajaOngkir ke kecamatan tujuan'], 'custom' => ['pencil', 'Ongkir manual', 'Isi kurir & ongkir sendiri'], 'pickup' => ['storefront', 'Ambil di toko', 'Tanpa ongkir']] as $value => [$icon, $title, $desc])
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 p-3 text-sm transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="shipping_mode" value="{{ $value }}" x-model="mode" class="mt-0.5 text-brand-600 focus:ring-brand-500/30">
                            <span><span class="flex items-center gap-1.5 font-semibold text-stone-800"><x-icon :name="$icon" class="size-4" /> {{ $title }}</span><span class="block text-xs text-stone-500">{{ $desc }}</span></span>
                        </label>
                    @endforeach
                </div>

                <div x-show="mode !== 'pickup'" class="space-y-4">
                    <div x-show="mode === 'courier'" class="space-y-4"
                         x-data="regionPicker({
                             urls: {
                                 provinces: @js(route('admin.orders.manual.regions.provinces')),
                                 cities: @js(route('admin.orders.manual.regions.cities', '__ID__')),
                                 districts: @js(route('admin.orders.manual.regions.districts', '__ID__')),
                             },
                             provinceId: @js(old('province_id')),
                             cityId: @js(old('city_id')),
                             districtId: @js(old('district_id')),
                             postalCode: @js(old('postal_code')),
                         })"
                         x-effect="setDistrict(districtId)">
                        @unless ($shippingReady)
                            <p class="flex gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"><x-icon name="warning" class="size-4 shrink-0" /> Kecamatan asal pengiriman belum diatur di Pengaturan Toko, jadi ongkir belum bisa dihitung.</p>
                        @endunless
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach ([['province', 'province_id', 'Provinsi'], ['city', 'city_id', 'Kota / Kabupaten'], ['district', 'district_id', 'Kecamatan']] as [$ref, $field, $text])
                                <x-form.field :label="$text" :for="$field" :name="$field">
                                    <select id="{{ $field }}" name="{{ $field }}" x-ref="{{ $ref }}"
                                            @class(['input', 'input-error' => $errors->has($field)])></select>
                                </x-form.field>
                            @endforeach
                            <x-form.input name="postal_code" label="Kode pos" optional x-model="postalCode" inputmode="numeric" maxlength="5" />
                        </div>
                        <p x-show="error" x-text="error" x-cloak class="rounded-xl bg-rose-50 p-3 text-xs text-rose-700"></p>
                    </div>

                    <x-form.textarea name="address_line" label="Alamat lengkap" rows="2" maxlength="500"
                                     placeholder="Nama jalan, nomor rumah, RT/RW, patokan" x-bind:required="mode !== 'pickup'" />

                    <div x-show="mode === 'courier'" class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <p class="label mb-0">Kurir</p>
                            <p class="text-xs text-stone-500">Berat <span x-text="((quote?.weight || 0) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 })"></span> kg</p>
                        </div>
                        <p x-show="!destination" class="text-sm text-stone-500">Pilih kecamatan tujuan untuk melihat ongkir.</p>
                        <p x-show="destination && !(quote?.weight)" x-cloak class="text-sm text-stone-500">Tambahkan produk dulu untuk menghitung berat.</p>
                        <p x-show="ratesLoading" x-cloak class="text-sm text-stone-500">Mengecek ongkir…</p>
                        <p x-show="ratesError" x-text="ratesError" x-cloak class="rounded-xl bg-rose-50 p-3 text-xs text-rose-700"></p>
                        <input type="hidden" name="rate" x-bind:value="mode === 'courier' ? rate : ''">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" x-show="rates.length && !ratesLoading" x-cloak>
                            <template x-for="option in rates" x-bind:key="option.key">
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-stone-200 p-3 text-sm transition"
                                       x-bind:class="rate === option.key ? 'border-brand-500 bg-brand-50' : 'hover:bg-stone-50'">
                                    <input type="radio" x-model="rate" x-bind:value="option.key" class="text-brand-600 focus:ring-brand-500/30">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-stone-800" x-text="`${option.courier} ${option.service}`"></span>
                                        <span class="block text-xs text-stone-500" x-text="option.etd ? `Estimasi ${option.etd}` : option.description"></span>
                                    </span>
                                    <span class="shrink-0 font-semibold tabular-nums" x-text="option.cost_label"></span>
                                </label>
                            </template>
                        </div>
                        <x-input-error for="rate" />
                    </div>

                    <div x-show="mode === 'custom'" x-cloak class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-form.input name="courier_name" label="Kurir / cara kirim" maxlength="30" x-model="courierName" placeholder="Mis. Gojek, Lalamove, kurir toko"
                                      x-bind:required="mode === 'custom'" />
                        <x-form.input name="shipping_cost" type="number" label="Ongkir" prefix="Rp" min="0" step="500" inputmode="numeric" x-model.number="shippingCost" />
                    </div>
                </div>
            </section>

            {{-- Promo & catatan --}}
            <section class="card space-y-4 p-5 sm:p-6">
                <h2 class="font-bold text-stone-900">Promo & catatan</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-form.input name="voucher_code" label="Kode voucher" optional x-model.lazy="voucherCode" class="uppercase" autocomplete="off"
                                      tooltip="Syarat voucher (minimal belanja, kuota, masa berlaku) dicek sama seperti checkout website. Kuota kembali bila pesanan batal." />
                        <p x-show="quote?.voucher_error" x-text="quote?.voucher_error" x-cloak class="mt-1 text-xs font-semibold text-rose-600"></p>
                    </div>
                    <x-form.input name="manual_discount" type="number" label="Diskon khusus" optional prefix="Rp" min="0" step="500" inputmode="numeric"
                                  x-model.number="manualDiscount"
                                  tooltip="Potongan harga dari staf di luar voucher (mis. nego). Tampil sebagai “Diskon khusus” di tagihan & invoice." />
                </div>
                <x-form.textarea name="notes" label="Catatan" optional rows="2" maxlength="1000" placeholder="Mis. pesanan via WhatsApp, minta dibungkus kado" />
            </section>
        </div>

        {{-- Ringkasan --}}
        <aside class="min-w-0">
            <div class="card sticky top-24 p-5">
                <h2 class="font-bold text-stone-900">Ringkasan</h2>
                <dl class="mt-4 space-y-2 text-sm" x-show="quote?.rows?.length && lines.length">
                    <template x-for="row in (quote?.rows || [])" x-bind:key="row.label">
                        <div class="flex justify-between gap-3"
                             x-bind:class="{ 'border-t border-stone-200 pt-2 text-base font-bold text-stone-900': row.tone === 'total', 'text-emerald-700': row.tone === 'discount', 'text-xs text-stone-500': row.tone === 'info', 'text-stone-600': row.tone === 'normal' }">
                            <dt><span x-text="row.label"></span> <span x-show="row.note" x-text="row.note ? `(${row.note})` : ''" class="font-mono text-xs"></span></dt>
                            <dd class="shrink-0 tabular-nums" x-text="row.amount"></dd>
                        </div>
                    </template>
                </dl>
                <p x-show="!lines.length" class="mt-4 text-sm text-stone-500">Tambahkan produk untuk melihat total.</p>
                <p class="mt-3 text-xs text-stone-500">Biaya transaksi QRIS / VA (bila dibebankan ke pembeli) ditambahkan saat tagihan dibuat.</p>

                <button class="btn btn-primary mt-5 w-full" x-bind:disabled="!canSubmit">
                    <x-icon name="check" class="size-4" /> Simpan & buat tagihan
                </button>
                <p x-show="mode === 'courier' && lines.length && !rate" x-cloak class="mt-2 text-center text-xs text-stone-500">Pilih kurir dulu.</p>
            </div>
        </aside>
    </form>
</x-layouts.admin>
