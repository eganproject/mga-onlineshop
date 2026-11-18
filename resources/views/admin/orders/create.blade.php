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
                               :tips="['Harga per pcs bisa diubah per baris (harga nego). Kosongkan untuk memakai harga web; harga yang diubah tidak memakai tingkat grosir.', 'Stok kembali otomatis bila pesanan dibatalkan atau tidak dibayar sampai batas waktu.']" />
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

                <ul class="@container mt-4 space-y-3" x-show="lines.length" x-cloak>
                    <template x-for="(line, index) in lines" x-bind:key="line.key">
                        <li class="rounded-2xl border border-stone-200 p-3 @lg:p-4" x-bind:class="problem(index) && 'border-rose-200 bg-rose-50/30'">
                            <input type="hidden" x-bind:name="`items[${index}][product_id]`" x-bind:value="line.product.id">
                            <input type="hidden" x-bind:name="`items[${index}][variant_id]`" x-bind:value="line.variantId">

                            {{-- Produk: foto, nama, info stok/harga, varian --}}
                            <div class="flex items-start gap-3">
                                <img x-show="line.product.image" x-bind:src="line.product.image" alt="" class="size-12 shrink-0 rounded-xl object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="line-clamp-2 text-sm font-semibold text-stone-900" x-text="line.product.name"></p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-1 gap-y-1 text-xs text-stone-500">
                                        <span>Stok <span x-text="stockOf(line)"></span></span>
                                        <template x-if="linePrice(index)">
                                            <span>· Harga web <span class="tabular-nums" x-text="rupiah(linePrice(index).web_price)"></span></span>
                                        </template>
                                        <span x-show="linePrice(index)?.regular_price" class="ml-1 badge bg-accent-50 text-accent-700">Grosir</span>
                                        <span x-show="linePrice(index)?.custom" class="ml-1 badge bg-brand-50 text-brand-700">Harga diubah</span>
                                    </p>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm -mt-1 -mr-1 shrink-0 p-2 text-stone-400 hover:text-rose-600" x-on:click="remove(index)" title="Hapus produk">
                                    <x-icon name="trash" class="size-4" /><span class="sr-only">Hapus produk</span>
                                </button>
                            </div>

                            {{-- Varian, harga, jumlah, subtotal: kolom sejajar dengan label --}}
                            <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3 @lg:grid-cols-[10rem_9rem_minmax(0,1fr)]"
                                 x-bind:class="line.product.has_variants && '@3xl:grid-cols-[minmax(0,1fr)_10rem_9rem_minmax(0,8rem)]'">
                                <template x-if="line.product.has_variants">
                                    <label class="col-span-2 block min-w-0 @lg:col-span-3 @3xl:col-span-1">
                                        <span class="mb-1 block text-xs font-semibold text-stone-600">Varian</span>
                                        <select x-model="line.variantId" class="input h-10 py-0 text-sm">
                                            <option value="">Pilih varian</option>
                                            <template x-for="variant in line.product.variants" x-bind:key="variant.id">
                                                <option x-bind:value="String(variant.id)" x-bind:selected="String(variant.id) === line.variantId"
                                                        x-text="`${variant.name} · ${variant.price_label} · stok ${variant.stock}${variant.active ? '' : ' (nonaktif)'}`"></option>
                                            </template>
                                        </select>
                                    </label>
                                </template>

                                <div class="min-w-0">
                                    <label x-bind:for="`price-${line.key}`" class="mb-1 block text-xs font-semibold text-stone-600">Harga / pcs</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-stone-400">Rp</span>
                                        <input x-bind:id="`price-${line.key}`" type="number" min="1" step="1" inputmode="numeric" x-model="line.price" x-bind:name="`items[${index}][price]`"
                                               x-bind:placeholder="linePrice(index) ? linePrice(index).web_price : ''"
                                               class="input h-10 py-0 pl-9 text-sm tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                    </div>
                                    <p class="mt-1 text-[11px] leading-4 text-stone-500">
                                        <span x-show="!(line.price !== '' && line.price !== null)">Kosong = harga web</span>
                                        <button type="button" x-show="line.price !== '' && line.price !== null" x-on:click="line.price = ''" class="link-underline font-semibold text-brand-700 hover:text-ink">Pakai harga web</button>
                                    </p>
                                </div>

                                <div class="min-w-0">
                                    <label x-bind:for="`qty-${line.key}`" class="mb-1 block text-xs font-semibold text-stone-600">Jumlah</label>
                                    <div class="flex h-10 items-stretch overflow-hidden rounded-xl border border-stone-300 bg-white focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                                        <button type="button" class="grid w-9 shrink-0 place-items-center text-stone-500 hover:bg-stone-50 hover:text-ink disabled:opacity-40" x-on:click="line.quantity = Math.max(1, (Number(line.quantity) || 1) - 1)" x-bind:disabled="Number(line.quantity) <= 1" aria-label="Kurangi"><x-icon name="minus" class="size-4" /></button>
                                        <input x-bind:id="`qty-${line.key}`" type="number" min="1" x-model.number="line.quantity" x-bind:name="`items[${index}][quantity]`"
                                               class="w-full min-w-0 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular-nums focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                        <button type="button" class="grid w-9 shrink-0 place-items-center text-stone-500 hover:bg-stone-50 hover:text-ink" x-on:click="line.quantity = (Number(line.quantity) || 0) + 1" aria-label="Tambah"><x-icon name="plus" class="size-4" /></button>
                                    </div>
                                </div>

                                <div class="col-span-2 flex items-center justify-between border-t border-stone-100 pt-3 @lg:col-span-1 @lg:block @lg:border-0 @lg:pt-0 @lg:text-right">
                                    <span class="block text-xs font-semibold text-stone-600 @lg:mb-1">Subtotal</span>
                                    <span class="block text-sm font-bold text-stone-900 tabular-nums @lg:flex @lg:h-10 @lg:items-center @lg:justify-end" x-text="linePrice(index) ? rupiah(linePrice(index).subtotal) : '—'"></span>
                                </div>
                            </div>

                            <p x-show="problem(index)" x-text="problem(index)" class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-rose-600"></p>
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
