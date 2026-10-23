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

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            <p class="font-semibold">Periksa kembali isian berikut:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

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
                <div>
                    <label for="name" class="label">Nama produk</label>
                    <input id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="255" placeholder="Contoh: Casing iPhone 15 Clear Magsafe" class="input @error('name') input-error @enderror">
                    <x-input-error for="name" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="category_id" class="label">Kategori</label>
                        <select id="category_id" name="category_id" class="input">
                            <option value="">Tanpa kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->parent_id ? '— '.$category->name : $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="category_id" />
                    </div>
                    <div>
                        <label for="sku" class="label">SKU induk <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="sku" name="sku" value="{{ old('sku', $product->sku) }}" maxlength="64" class="input @error('sku') input-error @enderror">
                        <x-input-error for="sku" />
                    </div>
                </div>
                <div>
                    <label for="description" class="label">Deskripsi</label>
                    <textarea id="description" name="description" rows="7" placeholder="Jelaskan spesifikasi, kompatibilitas, dan isi paket..." class="input">{{ old('description', $product->description) }}</textarea>
                    <x-input-error for="description" />
                </div>
                <div>
                    <label for="slug" class="label">Slug URL <span class="font-normal text-stone-400">(kosongkan untuk otomatis)</span></label>
                    <input id="slug" name="slug" value="{{ old('slug', $product->slug) }}" class="input @error('slug') input-error @enderror">
                    <x-input-error for="slug" />
                </div>
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
                    <div>
                        <label for="price" class="label">Harga jual</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-stone-400">Rp</span>
                            <input id="price" name="price" inputmode="numeric" value="{{ old('price', $product->price) }}" :required="!hasVariants" :disabled="hasVariants" class="input pl-10 @error('price') input-error @enderror">
                        </div>
                        <x-input-error for="price" />
                    </div>
                    <div>
                        <label for="compare_at_price" class="label">Harga coret <span class="font-normal text-stone-400">(opsional)</span></label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-stone-400">Rp</span>
                            <input id="compare_at_price" name="compare_at_price" inputmode="numeric" value="{{ old('compare_at_price', $product->compare_at_price) }}" :disabled="hasVariants" class="input pl-10">
                        </div>
                        <x-input-error for="compare_at_price" />
                    </div>
                    <div>
                        <label for="stock" class="label">Stok</label>
                        <input id="stock" type="number" min="0" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" :required="!hasVariants" :disabled="hasVariants" class="input @error('stock') input-error @enderror">
                        <input type="hidden" name="stock_original" value="{{ old('stock_original', $editing && ! $product->has_variants ? $product->stock : '') }}" :disabled="hasVariants">
                        @if ($editing)
                            <p class="mt-1.5 text-xs text-stone-400">Perubahan tercatat di riwayat stok.</p>
                        @endif
                        <x-input-error for="stock" />
                    </div>
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
                                    <input x-model="bulkPrice" inputmode="numeric" placeholder="Harga" class="input">
                                    <input x-model="bulkStock" type="number" min="0" placeholder="Stok" class="input">
                                </div>
                            </div>
                            <button type="button" @click="applyBulk()" class="btn btn-secondary">Terapkan</button>
                        </div>
                    </div>

                    <x-input-error for="variants" />
                    <div class="-mx-6 overflow-x-auto">
                        <table class="table-base min-w-[860px]">
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
                                        <td><input :name="`variants[${index}][price]`" x-model="variant.price" inputmode="numeric" required :disabled="!hasVariants" class="input min-w-28"></td>
                                        <td><input :name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price" inputmode="numeric" :disabled="!hasVariants" class="input min-w-28"></td>
                                        <td>
                                            <input type="number" min="0" :name="`variants[${index}][stock]`" x-model="variant.stock" required :disabled="!hasVariants" class="input w-24">
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
                <label for="weight" class="label">Berat (gram)</label>
                <input id="weight" type="number" min="1" name="weight" value="{{ old('weight', $product->weight) }}" required class="input @error('weight') input-error @enderror">
                <p class="mt-1.5 text-xs text-stone-400">Dipakai untuk menghitung ongkos kirim. Varian bisa punya berat sendiri.</p>
                <x-input-error for="weight" />
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
              onsubmit="return confirm(@js('Hapus produk '.$product->name.' beserta semua foto dan variannya?'))">
            @csrf
            @method('DELETE')
        </form>
    @endif
</x-layouts.admin>
