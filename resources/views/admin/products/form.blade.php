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
    ])->values()->all();
    $variantRows = collect($variantRows)->map(fn ($v) => array_merge([
        'id' => null, 'name' => '', 'sku' => '', 'price' => '', 'compare_at_price' => '', 'stock' => 0,
        'stock_original' => null, 'weight' => '', 'image_id' => '', 'is_active' => false,
    ], $v, ['is_active' => (bool) ($v['is_active'] ?? false), 'image_id' => (string) ($v['image_id'] ?? '')]))->values();
    $formConfig = [
        'hasVariants' => (bool) old('has_variants', $product->has_variants),
        'images' => $product->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url(), 'removed' => false])->values(),
        'variants' => $variantRows,
        'defaultPrice' => old('price', $product->price),
        'maxImages' => \App\Http\Requests\Admin\ProductRequest::MAX_IMAGES,
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
          class="grid gap-6 lg:grid-cols-3"
          x-data="productForm(@js($formConfig))">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Informasi --}}
            <div class="card space-y-5 p-6">
                <h2 class="font-bold text-stone-900">Informasi produk</h2>
                <x-form.input name="name" label="Nama produk" :value="$product->name" required maxlength="255" placeholder="Contoh: Frame Akrilik Dinding A4" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.category-select name="category_id" label="Kategori" :categories="$categories" :selected="$product->category_id" placeholder="Tanpa kategori" />
                    <x-form.input name="sku" label="SKU induk" optional :value="$product->sku" maxlength="64" />
                </div>
                <x-form.textarea name="description" label="Deskripsi" :value="$product->description" rows="7" maxlength="10000"
                                 placeholder="Jelaskan spesifikasi, kompatibilitas, dan isi paket..." />
                <x-form.input name="slug" label="Slug URL" optional="kosongkan untuk otomatis" :value="$product->slug" />
            </div>

            {{-- Galeri --}}
            <div class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-stone-900">Foto produk</h2>
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
                    <label class="flex items-center gap-2 text-sm font-semibold text-stone-700">
                        <input type="hidden" name="has_variants" value="0">
                        <input type="checkbox" name="has_variants" value="1" x-model="hasVariants" @change="toggleVariants()" class="checkbox size-5">
                        Produk punya varian
                    </label>
                </div>

                {{-- Tanpa varian --}}
                <div x-show="!hasVariants" class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-form.money name="price" label="Harga jual" :value="$product->price" x-bind:required="!hasVariants" x-bind:disabled="hasVariants" />
                    <x-form.money name="compare_at_price" label="Harga coret" optional :value="$product->compare_at_price" x-bind:disabled="hasVariants"
                                  hint="Harga sebelum diskon, tampil dicoret." />
                    <x-form.number name="stock" label="Stok" :value="$product->stock ?? 0" min="0" suffix="pcs" x-bind:required="!hasVariants" x-bind:disabled="hasVariants"
                                   :hint="$editing ? 'Perubahan tercatat di riwayat stok.' : null" />
                    <input type="hidden" name="stock_original" value="{{ old('stock_original', $editing && ! $product->has_variants ? $product->stock : '') }}" :disabled="hasVariants">
                </div>

                {{-- Dengan varian --}}
                <div x-show="hasVariants" x-cloak class="mt-5 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="variant_label" class="label">Judul pilihan</label>
                            <input id="variant_label" name="variant_label" value="{{ old('variant_label', $product->variant_label) }}" maxlength="50" placeholder="Contoh: Warna, Tipe HP" :disabled="!hasVariants" class="input">
                            <x-input-error for="variant_label" />
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <label class="label">Isi semua varian</label>
                                <div class="flex gap-2">
                                    <input x-model="bulkPrice" x-money placeholder="Harga" class="input">
                                    <input x-model="bulkStock" type="number" min="0" placeholder="Stok" class="input">
                                </div>
                            </div>
                            <button type="button" @click="applyBulk()" class="btn btn-secondary">Terapkan</button>
                        </div>
                    </div>

                    <x-input-error for="variants" />
                    <div class="-mx-6 overflow-x-auto">
                        <table class="table-base min-w-[940px]">
                            <thead class="bg-stone-50/70">
                                <tr><th class="w-8"></th><th>Nama varian</th><th>SKU</th><th>Harga</th><th>Harga coret</th><th>Stok</th><th>Berat (gr)</th><th>Foto</th><th class="text-center">Aktif</th><th></th></tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100" x-ref="variantRows">
                                <template x-for="(variant, index) in variants" :key="variant.uid">
                                    <tr class="align-top">
                                        <td class="pt-5 text-xs font-semibold text-stone-400" x-text="index + 1"></td>
                                        <td>
                                            <input type="hidden" :name="`variants[${index}][id]`" :value="variant.id ?? ''" :disabled="!hasVariants">
                                            <input type="hidden" :name="`variants[${index}][stock_original]`" :value="variant.stock_original ?? ''" :disabled="!hasVariants">
                                            <input :name="`variants[${index}][name]`" x-model="variant.name" required maxlength="100" placeholder="Hitam" :disabled="!hasVariants" class="input min-w-36">
                                        </td>
                                        <td><input :name="`variants[${index}][sku]`" x-model="variant.sku" maxlength="64" :disabled="!hasVariants" class="input min-w-28"></td>
                                        <td><div class="relative"><span class="input-addon left-3">Rp</span><input :name="`variants[${index}][price]`" x-model="variant.price" x-money required :disabled="!hasVariants" class="input min-w-32 pl-9 tabular-nums"></div></td>
                                        <td><div class="relative"><span class="input-addon left-3">Rp</span><input :name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price" x-money :disabled="!hasVariants" class="input min-w-32 pl-9 tabular-nums"></div></td>
                                        <td>
                                            <x-form.number wrapper-class="w-32" min="0" x-bind:name="`variants[${index}][stock]`" x-model="variant.stock" required x-bind:disabled="!hasVariants" />
                                        </td>
                                        <td><input type="number" min="1" :name="`variants[${index}][weight]`" x-model="variant.weight" placeholder="Ikut produk" :disabled="!hasVariants" class="input w-28"></td>
                                        <td>
                                            <select :name="`variants[${index}][image_id]`" x-model="variant.image_id" :disabled="!hasVariants" class="input min-w-28">
                                                <option value="">Sampul</option>
                                                <template x-for="(image, i) in activeImages" :key="image.id">
                                                    <option :value="String(image.id)" x-text="`Foto ${i + 1}`" :selected="String(image.id) === String(variant.image_id)"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="pt-4 text-center">
                                            <input type="checkbox" value="1" :name="`variants[${index}][is_active]`" x-model="variant.is_active" :disabled="!hasVariants" class="checkbox size-5">
                                        </td>
                                        <td class="pt-3">
                                            <button type="button" @click="removeVariant(index)" class="btn btn-ghost btn-sm text-rose-600 hover:bg-rose-50" title="Hapus varian"><x-icon name="trash" class="size-4" /></button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    @foreach ($errors->get('variants.*') as $messages)
                        <p class="text-xs font-medium text-rose-600">{{ $messages[0] }}</p>
                    @endforeach
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button type="button" @click="addVariant()" class="btn btn-secondary"><x-icon name="plus" class="size-4" /> Tambah varian</button>
                        <p class="text-sm text-stone-500">Total stok aktif: <span class="font-bold text-stone-900" x-text="totalStock"></span></p>
                    </div>
                    <p class="text-xs text-stone-400">Foto varian dipilih dari foto yang sudah tersimpan. Harga produk di katalog otomatis memakai varian aktif termurah.</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card space-y-4 p-6">
                <h2 class="font-bold text-stone-900">Visibilitas</h2>
                <label class="flex items-center justify-between gap-3">
                    <span><span class="block text-sm font-semibold text-stone-800">Aktif</span><span class="text-xs text-stone-500">Tampil dan bisa dibeli</span></span>
                    <input type="checkbox" name="is_active" value="1" class="checkbox size-5" @checked(old('is_active', $product->is_active))>
                </label>
                <label class="flex items-center justify-between gap-3">
                    <span><span class="block text-sm font-semibold text-stone-800">Produk unggulan</span><span class="text-xs text-stone-500">Tampil di beranda</span></span>
                    <input type="checkbox" name="is_featured" value="1" class="checkbox size-5" @checked(old('is_featured', $product->is_featured))>
                </label>
            </div>

            <div class="card p-6">
                <x-form.number name="weight" label="Berat" :value="$product->weight" min="1" suffix="gram" required
                               hint="Dipakai untuk menghitung ongkos kirim. Varian bisa punya berat sendiri." />
            </div>

            <div class="lg:sticky lg:top-24">
                <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan produk' }}</button>
                @if ($editing)
                    {{-- Form hapus ada di luar form utama (form tidak boleh bersarang). --}}
                    <button form="delete-product-form" class="btn btn-ghost mt-3 w-full text-rose-600 hover:bg-rose-50"><x-icon name="trash" class="size-4" /> Hapus produk</button>
                @endif
            </div>
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
