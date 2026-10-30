@php
    $editing = $product->exists;
    $variantRows = old('variants') ?? $product->variants->map(fn ($v) => [
        'id' => $v->id,
        'name' => $v->name,
        'sku' => $v->sku,
        'price' => $v->price,
        'compare_at_price' => $v->compare_at_price,
        'stock' => $v->stock,
        'stock_original' => $v->stock,
        'weight' => $v->weight,
        'image_id' => $v->product_image_id,
        'is_active' => $v->is_active,
        'wholesale_tiers' => $v->wholesale_tiers ?? [],
    ])->values()->all();
    $variantRows = collect($variantRows)->map(fn ($v) => array_merge([
        'id' => null, 'name' => '', 'sku' => '', 'price' => '', 'compare_at_price' => '', 'stock' => 0,
        'stock_original' => null, 'weight' => '', 'image_id' => '', 'is_active' => false,
    ], $v, [
        'is_active' => (bool) ($v['is_active'] ?? false),
        'image_id' => (string) ($v['image_id'] ?? ''),
        'tiers' => array_values((array) ($v['wholesale_tiers'] ?? [])),
    ]))->values();
    $formConfig = [
        'hasVariants' => (bool) old('has_variants', $product->has_variants),
        'images' => $product->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url(), 'removed' => false])->values(),
        'variants' => $variantRows,
        'defaultPrice' => old('price', $product->price),
        'tiers' => array_values((array) old('wholesale_tiers', $product->has_variants ? [] : ($product->wholesale_tiers ?? []))),
        'maxImages' => \App\Http\Requests\Admin\ProductRequest::MAX_IMAGES,
        // Pesan error per baris varian: [index => [pesan, ...]].
        'variantErrors' => collect($errors->getMessages())
            ->filter(fn ($m, $key) => preg_match('/^variants\.\d+\./', $key))
            ->groupBy(fn ($m, $key) => (int) explode('.', $key)[1], preserveKeys: true)
            ->map(fn ($group) => $group->flatten()->unique()->values()),
    ];
@endphp
<x-layouts.admin :title="$editing ? 'Edit Produk' : 'Tambah Produk'">
    <x-admin.page-header :title="$editing ? 'Edit produk' : 'Tambah produk'" :back="route('admin.products.index')">
        @if ($editing)
            <a href="{{ route('products.show', $product) }}" target="_blank" class="btn btn-secondary"><x-icon name="eye" class="size-4" /> Lihat di toko</a>
            <a href="{{ route('admin.stock.history', ['produk' => $product->id]) }}" class="btn btn-secondary"><x-icon name="clock" class="size-4" /> Riwayat stok</a>
        @endif
    </x-admin.page-header>

    <x-form.errors />

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}"
          class="grid grid-cols-1 gap-6 lg:grid-cols-3"
          x-data="productForm(@js($formConfig))">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Informasi --}}
            <div class="card space-y-5 p-6">
                <h2 class="font-bold text-stone-900">Informasi produk</h2>
                <x-form.input name="name" label="Nama produk" :value="$product->name" required maxlength="255" placeholder="Contoh: Frame Akrilik Dinding A4" />
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-form.category-select name="category_id" label="Kategori" :categories="$categories" :selected="$product->category_id" placeholder="Tanpa kategori" />
                    <x-form.input name="sku" label="SKU induk" optional :value="$product->sku" maxlength="64"
                                  tooltip="Kode unik produk untuk gudang (Stock Keeping Unit). Tidak tampil sebagai nama ke pelanggan, tapi bisa dicari di admin."
                                  :tooltip-tips="['Pakai pola konsisten, mis. CSG-IP15-CLR (jenis-tipe-warna).', 'SKU jadi kunci saat impor produk & update stok massal lewat Excel, jadi jangan sampai dobel.']" />
                </div>
                <x-form.textarea name="description" label="Deskripsi" :value="$product->description" rows="7" maxlength="10000"
                                 placeholder="Jelaskan spesifikasi, kompatibilitas, dan isi paket..."
                                 tooltip="Tampil di halaman detail produk, di bawah tombol beli."
                                 :tooltip-tips="['Sebut tipe HP yang cocok, bahan, ukuran, dan isi paket.', 'Paragraf pendek & poin-poin lebih mudah dibaca di HP.']" />
                <x-form.input name="slug" label="Slug URL" optional="kosongkan untuk otomatis" :value="$product->slug"
                              tooltip="Bagian akhir alamat halaman produk, mis. /produk/frame-akrilik-a4. Dibuat otomatis dari nama bila kosong."
                              :tooltip-tips="['Huruf kecil, angka, dan tanda hubung saja.', 'Hindari mengubah slug produk yang sudah dibagikan: link lama jadi tidak bisa dibuka.']" />
            </div>

            {{-- Galeri --}}
            <div class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-1">
                        <h2 class="font-bold text-stone-900">Foto produk</h2>
                        <x-tooltip label="Foto produk" text="Foto pertama (sampul) tampil di kartu produk: katalog, Best Seller, pencarian, dan keranjang. Semua foto tampil di galeri halaman produk."
                                   :tips="['Rasio 1:1 (persegi), min. 1000×1000 px supaya tetap tajam saat diperbesar.', 'Taruh produk di tengah dengan ruang kosong ±10% di kiri-kanan: di katalog desktop foto dipotong jadi 4:5, di HP tampil persegi.', 'Latar polos & terang dengan cahaya rata membuat katalog terlihat rapi.', 'Foto berikutnya: sisi belakang, detail, dan contoh saat dipakai.']" />
                    </div>
                    <p class="text-xs text-stone-500">Foto pertama jadi sampul. Maks {{ $formConfig['maxImages'] }} foto, JPG/PNG/WebP 2 MB.</p>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                    <template x-for="(image, index) in images" :key="image.id">
                        <div class="group relative aspect-square overflow-hidden rounded-2xl border border-stone-200 bg-stone-50"
                             :class="image.removed && 'opacity-40'">
                            <img :src="image.url" alt="" class="size-full object-cover">
                            <input type="hidden" name="image_order[]" :value="image.id">
                            <template x-if="image.removed"><input type="hidden" name="remove_images[]" :value="image.id"></template>
                            <span x-show="index === images.findIndex(i => !i.removed) && !image.removed" class="absolute left-2 top-2 rounded-lg bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">Sampul</span>
                            <div class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-gradient-to-t from-stone-900/70 p-1.5">
                                <div class="flex gap-1">
                                    <button type="button" @click="move(index, -1)" :disabled="index === 0" class="rounded-lg bg-white/90 p-1 text-stone-700 disabled:opacity-30" title="Geser ke kiri"><x-icon name="chevron-left" class="size-3.5" /></button>
                                    <button type="button" @click="move(index, 1)" :disabled="index === images.length - 1" class="rounded-lg bg-white/90 p-1 text-stone-700 disabled:opacity-30" title="Geser ke kanan"><x-icon name="chevron-right" class="size-3.5" /></button>
                                    <button type="button" @click="makeCover(index)" x-show="index !== 0 && !image.removed" class="rounded-lg bg-white/90 p-1 text-amber-600" title="Jadikan sampul"><x-icon name="star" class="size-3.5" /></button>
                                </div>
                                <button type="button" @click="image.removed = !image.removed" class="rounded-lg bg-white/90 p-1" :class="image.removed ? 'text-stone-700' : 'text-rose-600'" :title="image.removed ? 'Batal hapus' : 'Hapus foto'">
                                    <span x-show="!image.removed"><x-icon name="trash" class="size-3.5" /></span>
                                    <span x-show="image.removed"><x-icon name="refresh" class="size-3.5" /></span>
                                </button>
                            </div>
                        </div>
                    </template>
                    <template x-for="upload in uploads" :key="upload.url">
                        <div class="relative aspect-square overflow-hidden rounded-2xl border-2 border-dashed border-brand-300">
                            <img :src="upload.url" :alt="upload.name" class="size-full object-cover">
                            <span class="absolute left-2 top-2 rounded-lg bg-stone-900/70 px-2 py-0.5 text-[10px] font-semibold text-white">Baru</span>
                        </div>
                    </template>
                    <label x-show="remainingSlots > 0" class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-stone-200 bg-stone-50 text-stone-400 transition hover:border-brand-300 hover:bg-brand-50/40 hover:text-brand-600">
                        <x-icon name="plus" class="size-6" />
                        <span class="text-xs font-semibold">Tambah foto</span>
                        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="uploadInput" @change="previewUploads($event)">
                    </label>
                </div>
                <p x-show="uploads.length" class="mt-3 text-xs text-stone-500">
                    <span x-text="uploads.length"></span> foto baru akan diunggah saat disimpan.
                    <button type="button" @click="clearUploads()" class="font-semibold text-rose-600 hover:underline">Batalkan</button>
                </p>
                <p x-show="remainingSlots < 0" class="mt-2 text-xs font-semibold text-rose-600">Jumlah foto melebihi batas. Kurangi foto yang dipilih.</p>
                <x-input-error for="images" />
                @foreach ($errors->get('images.*') as $messages)
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $messages[0] }}</p>
                @endforeach
            </div>

            {{-- Harga, stok & varian --}}
            <div class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-stone-900">Harga, stok & varian</h2>
                        <p class="text-xs text-stone-500">Aktifkan varian bila produk punya pilihan warna, tipe HP, ukuran, dll.</p>
                    </div>
                    <x-form.switch name="has_variants" label="Produk punya varian" x-model="hasVariants" @change="toggleVariants()" :checked="old('has_variants', $product->has_variants)"
                                   tooltip="Aktifkan bila satu produk punya beberapa pilihan, mis. warna atau tipe HP. Tiap varian punya harga, stok, SKU, berat, dan foto sendiri; pelanggan wajib memilih sebelum membeli." />
                </div>

                {{-- Tanpa varian --}}
                <div x-show="!hasVariants" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-form.money name="price" label="Harga jual" :value="$product->price" x-bind:required="!hasVariants" x-bind:disabled="hasVariants" x-on:input="basePrice = $event.target.value" />
                    <x-form.money name="compare_at_price" label="Harga coret" optional :value="$product->compare_at_price" x-bind:disabled="hasVariants"
                                  hint="Harga sebelum diskon, tampil dicoret."
                                  tooltip="Bila lebih tinggi dari harga jual, kartu produk menampilkan harga ini dicoret plus label diskon oranye, mis. -20%."
                                  tooltip-tips="Kosongkan bila produk sedang tidak promo, supaya label diskon tidak menyesatkan." />
                    <x-form.number name="stock" label="Stok" :value="$product->stock ?? 0" min="0" suffix="pcs" x-bind:required="!hasVariants" x-bind:disabled="hasVariants"
                                   :hint="$editing ? 'Perubahan tercatat di riwayat stok.' : null"
                                   tooltip="Jumlah barang siap jual. Berkurang otomatis saat pelanggan membuat pesanan dan kembali bila pesanan batal/kedaluwarsa. Stok 0 = tampil “Habis”." />
                    <input type="hidden" name="stock_original" value="{{ old('stock_original', $editing && ! $product->has_variants ? $product->stock : '') }}" :disabled="hasVariants">
                </div>

                {{-- Dengan varian --}}
                <div x-show="hasVariants" x-cloak class="mt-5 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <div class="label-row">
                                <label for="variant_label" class="label">Judul pilihan</label>
                                <x-tooltip label="Judul pilihan" text="Nama jenis pilihan di halaman produk & keranjang, mis. “Warna: Hitam” atau “Tipe HP: iPhone 15”. Kosong = “Varian”." />
                            </div>
                            <input id="variant_label" name="variant_label" value="{{ old('variant_label', $product->variant_label) }}" maxlength="50" placeholder="Contoh: Warna, Tipe HP" :disabled="!hasVariants" class="input">
                            <x-input-error for="variant_label" />
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <div class="label-row">
                                    <label class="label">Isi semua varian</label>
                                    <x-tooltip label="Isi semua varian" text="Jalan pintas: isi harga dan/atau stok, lalu tekan Terapkan untuk menyalinnya ke semua baris varian sekaligus." />
                                </div>
                                <div class="flex gap-2">
                                    <input x-model="bulkPrice" x-money placeholder="Harga" class="input">
                                    <input x-model="bulkStock" type="number" min="0" placeholder="Stok" class="input">
                                </div>
                            </div>
                            <button type="button" @click="applyBulk()" class="btn btn-secondary">Terapkan</button>
                        </div>
                    </div>

                    <x-input-error for="variants" />
                    {{-- Tiap varian = kartu. Kolom menyesuaikan lebar kartu (container query), jadi tidak perlu geser ke samping. --}}
                    <div class="space-y-3" x-ref="variantRows">
                        <template x-for="(variant, index) in variants" :key="variant.uid">
                            <div class="@container rounded-2xl border p-3 transition sm:p-4"
                                 :class="[variant.is_active ? 'border-stone-200 bg-white' : 'border-dashed border-stone-200 bg-stone-50/70', rowErrors(index).length && 'border-rose-300 ring-4 ring-rose-500/10']"
                                 data-variant-row>
                                <input type="hidden" :name="`variants[${index}][id]`" :value="variant.id ?? ''" :disabled="!hasVariants">
                                <input type="hidden" :name="`variants[${index}][stock_original]`" :value="variant.stock_original ?? ''" :disabled="!hasVariants">

                                <div class="flex flex-wrap items-center gap-2 @md:flex-nowrap @md:gap-3">
                                    <span class="inline-flex h-7 min-w-7 shrink-0 items-center justify-center rounded-lg bg-stone-100 px-2 text-xs font-bold whitespace-nowrap text-stone-500"><span class="@md:hidden">Varian&nbsp;</span><span x-text="index + 1"></span></span>
                                    <label class="sr-only" :for="`variant-${variant.uid}-name`">Nama varian</label>
                                    <input :id="`variant-${variant.uid}-name`" :name="`variants[${index}][name]`" x-model="variant.name" required maxlength="100"
                                           placeholder="Nama varian, mis. Hitam" :disabled="!hasVariants" class="input order-last min-w-0 basis-full font-semibold @md:order-none @md:basis-auto @md:flex-1">
                                    <label class="ml-auto flex shrink-0 cursor-pointer items-center gap-2 rounded-xl px-1.5 py-2 text-xs font-semibold text-stone-600 @md:ml-0 @md:px-2" :title="variant.is_active ? 'Varian aktif' : 'Varian nonaktif'">
                                        <span x-text="variant.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                        <input type="checkbox" role="switch" value="1" :name="`variants[${index}][is_active]`" x-model="variant.is_active" :disabled="!hasVariants" class="switch">
                                    </label>
                                    <button type="button" @click="removeVariant(index)" class="btn btn-ghost btn-sm shrink-0 px-2 text-rose-600 hover:bg-rose-50" :aria-label="`Hapus varian ${variant.name || index + 1}`" title="Hapus varian"><x-icon name="trash" class="size-4" /></button>
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3 @xl:grid-cols-3 @4xl:grid-cols-6">
                                    <div class="min-w-0">
                                        <label class="mb-1 block text-xs font-semibold text-stone-500" :for="`variant-${variant.uid}-price`">Harga</label>
                                        <div class="relative"><span class="input-addon left-3">Rp</span><input :id="`variant-${variant.uid}-price`" :name="`variants[${index}][price]`" x-model="variant.price" x-money required :disabled="!hasVariants" class="input pl-9 tabular-nums"></div>
                                    </div>
                                    <div class="min-w-0">
                                        <label class="mb-1 block text-xs font-semibold text-stone-500" :for="`variant-${variant.uid}-compare`">Harga coret</label>
                                        <div class="relative"><span class="input-addon left-3">Rp</span><input :id="`variant-${variant.uid}-compare`" :name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price" x-money placeholder="Opsional" :disabled="!hasVariants" class="input pl-9 tabular-nums"></div>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="mb-1 block text-xs font-semibold text-stone-500">Stok</span>
                                        <x-form.number min="0" x-bind:name="`variants[${index}][stock]`" x-model="variant.stock" required x-bind:disabled="!hasVariants" aria-label="Stok" />
                                    </div>
                                    <div class="min-w-0">
                                        <label class="mb-1 flex items-center gap-1 text-xs font-semibold text-stone-500" :for="`variant-${variant.uid}-sku`">SKU <x-tooltip label="SKU varian" text="Kode unik tiap varian, dipakai untuk update stok massal lewat Excel. Boleh kosong." /></label>
                                        <input :id="`variant-${variant.uid}-sku`" :name="`variants[${index}][sku]`" x-model="variant.sku" maxlength="64" placeholder="Opsional" :disabled="!hasVariants" class="input">
                                    </div>
                                    <div class="min-w-0">
                                        <label class="mb-1 flex items-center gap-1 text-xs font-semibold text-stone-500" :for="`variant-${variant.uid}-weight`">Berat (gram) <x-tooltip label="Berat varian" text="Isi bila berat varian ini berbeda. Kosongkan untuk memakai berat produk." /></label>
                                        <input :id="`variant-${variant.uid}-weight`" type="number" min="1" inputmode="numeric" :name="`variants[${index}][weight]`" x-model="variant.weight" placeholder="Ikut produk" :disabled="!hasVariants" class="input tabular-nums">
                                    </div>
                                    <div class="min-w-0">
                                        <label class="mb-1 flex items-center gap-1 text-xs font-semibold text-stone-500" :for="`variant-${variant.uid}-image`">Foto <x-tooltip label="Foto varian" text="Foto yang ditampilkan saat pelanggan memilih varian ini. Pilihan diambil dari foto yang sudah tersimpan, jadi simpan dulu foto baru." /></label>
                                        <div class="flex items-center gap-2">
                                            <img x-show="variantImage(variant)" :src="variantImage(variant)?.url" alt="" class="size-10 shrink-0 rounded-lg border border-stone-200 object-cover">
                                            <select :id="`variant-${variant.uid}-image`" :name="`variants[${index}][image_id]`" x-model="variant.image_id" :disabled="!hasVariants" class="input min-w-0">
                                                <option value="">Sampul</option>
                                                <template x-for="(image, i) in activeImages" :key="image.id">
                                                    <option :value="String(image.id)" x-text="`Foto ${i + 1}`" :selected="String(image.id) === String(variant.image_id)"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <details class="group/tiers mt-3 rounded-xl border border-stone-200 bg-stone-50/60 px-3 py-2" :open="variant.tiers.length > 0">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 py-1 text-xs font-semibold text-stone-600">
                                        <span class="flex items-center gap-1.5"><x-icon name="tag" class="size-3.5 text-accent-600" /> Harga grosir
                                            <span class="font-normal text-stone-400" x-text="variant.tiers.length ? `(${variant.tiers.length} tingkat)` : '(belum ada)'"></span></span>
                                        <x-icon name="chevron-down" class="size-4 transition group-open/tiers:rotate-180" />
                                    </summary>
                                    <div class="mt-2 pb-1">
                                        <x-admin.wholesale-tiers list="variant.tiers" name="`variants[${index}][wholesale_tiers]`" base="variant.price" disabled="!hasVariants" />
                                        <button type="button" x-show="variants.length > 1" @click="copyTiers(index)" class="mt-2 text-xs font-semibold text-brand-700 hover:underline">Salin ke semua varian</button>
                                    </div>
                                </details>

                                <template x-for="message in rowErrors(index)" :key="message">
                                    <p class="mt-2 text-xs font-medium text-rose-600" x-text="message"></p>
                                </template>
                            </div>
                        </template>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button type="button" @click="addVariant()" class="btn btn-secondary"><x-icon name="plus" class="size-4" /> Tambah varian</button>
                        <p class="text-sm text-stone-500">Total stok aktif: <span class="font-bold text-stone-900" x-text="totalStock"></span></p>
                    </div>
                    <p class="text-xs text-stone-400">Foto varian dipilih dari foto yang sudah tersimpan. Harga produk di katalog otomatis memakai varian aktif termurah.</p>
                </div>
            </div>

            {{-- Grosir & minimal pembelian --}}
            <div class="card space-y-5 p-6">
                <div>
                    <div class="flex items-center gap-1.5">
                        <h2 class="font-bold text-stone-900">Harga grosir & minimal pembelian</h2>
                        <x-tooltip label="Harga grosir" text="Harga per pcs otomatis turun saat jumlah di keranjang mencapai tingkat tertentu. Jumlah dihitung gabungan semua varian produk ini (5 Hitam + 5 Putih = 10 pcs)."
                                   :tips="['Buat 2–3 tingkat yang mudah diingat, mis. 10, 50, 100 pcs.', 'Harga tiap tingkat harus lebih murah dari tingkat sebelumnya.', 'Pembeli yang butuh lebih banyak bisa minta harga khusus lewat tombol WhatsApp di halaman produk.']" />
                    </div>
                    <p class="text-xs text-stone-500">Tampil sebagai tabel harga di halaman produk dan label “Grosir” di katalog.</p>
                </div>

                <div x-show="!hasVariants">
                    <x-admin.wholesale-tiers list="tiers" name="'wholesale_tiers'" base="basePrice" disabled="hasVariants" />
                </div>
                <p x-show="hasVariants" x-cloak class="rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-600">
                    Produk ini punya varian: atur harga grosir di tiap kartu varian di atas (bagian “Harga grosir”), lalu pakai “Salin ke semua varian” bila sama.
                </p>
                @foreach ($errors->get('wholesale_tiers*') as $messages)
                    <p class="text-xs font-medium text-rose-600">{{ $messages[0] }}</p>
                @endforeach

                <x-form.number name="min_order_qty" label="Minimal pembelian" :value="$product->min_order_qty ?? 1" min="1" max="999" suffix="pcs" wrapper-class="sm:max-w-xs"
                               hint="1 = bisa beli satuan."
                               tooltip="Jumlah paling sedikit per pesanan untuk produk ini (gabungan semua varian). Keranjang menolak checkout bila kurang."
                               :tooltip-tips="['Biarkan 1 bila produk juga dijual eceran.', 'Untuk produk khusus grosir, samakan dengan tingkat grosir pertama.']" />
            </div>

            <x-admin.seo-fields :model="$product"
                :url="url('/produk').'/'.($product->slug ?: 'nama-produk')"
                :title-fallback="$product->name ?: 'Nama produk'"
                :description-fallback="$editing ? $product->metaDescription() : 'Kosong = memakai awal deskripsi produk.'"
                :title-tips="['Mulai dengan kata yang dicari pembeli, lalu ukuran/bahan, mis. “Frame Akrilik A4 Dinding – Bening 3mm”.']"
                :description-tips="['Sebut keunggulan & alasan beli: harga gudang, bisa grosir, kirim ke seluruh Indonesia.']" />
        </div>

        <div class="space-y-6">
            <div class="card space-y-4 p-6">
                <h2 class="font-bold text-stone-900">Visibilitas</h2>
                <x-form.switch name="is_active" label="Aktif" description="Tampil di toko dan bisa dibeli. Matikan untuk menyembunyikan." :checked="$product->is_active" />
                <x-form.switch name="is_featured" label="Produk unggulan" description="Diprioritaskan di tab Best Seller beranda." :checked="$product->is_featured"
                               tooltip="Dipakai melengkapi tab Best Seller di beranda (mode Otomatis) saat data penjualan belum cukup, didahulukan sebelum produk terbaru." />
            </div>

            <div class="card p-6">
                <x-form.number name="weight" label="Berat" :value="$product->weight" min="1" suffix="gram" required
                               hint="Dipakai untuk menghitung ongkos kirim. Varian bisa punya berat sendiri."
                               tooltip="Ongkir di checkout dihitung dari total berat semua barang di keranjang ke alamat pelanggan."
                               :tooltip-tips="['Isi berat setelah dikemas (produk + kardus/bubble wrap), bukan berat bersih.', 'Berat terlalu kecil membuat ongkir yang dibayar pelanggan kurang dari tagihan kurir.']" />
            </div>

            <x-admin.save-bar class="lg:sticky lg:top-24">
                <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan produk' }}</button>
            </x-admin.save-bar>
            @if ($editing)
                {{-- Form hapus ada di luar form utama (form tidak boleh bersarang). --}}
                <button form="delete-product-form" class="btn btn-ghost w-full text-rose-600 hover:bg-rose-50 lg:-mt-3"><x-icon name="trash" class="size-4" /> Hapus produk</button>
            @endif
        </div>
    </form>

    @if ($editing)
        <form id="delete-product-form" method="POST" action="{{ route('admin.products.destroy', $product) }}" class="hidden"
              data-confirm-title="Hapus produk?" data-confirm-ok="Ya, hapus"
              data-confirm="{{ $product->name }} beserta foto, varian, ulasan, dan riwayat stoknya akan dihapus permanen. Pesanan lama tetap tersimpan.">
            @csrf
            @method('DELETE')
        </form>
    @endif
</x-layouts.admin>
