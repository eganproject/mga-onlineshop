@php($editing = $product->exists)
<x-layouts.admin :title="$editing ? 'Edit Produk' : 'Tambah Produk'">
    <x-admin.page-header :title="$editing ? 'Edit produk' : 'Tambah produk'" :back="route('admin.products.index')" />

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}"
          class="grid gap-6 lg:grid-cols-3"
          x-data="{ preview: @js($product->imageUrl()), removeImage: false }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <div class="card space-y-5 p-6">
                <h2 class="font-bold text-stone-900">Informasi produk</h2>
                <div>
                    <label for="name" class="label">Nama produk</label>
                    <input id="name" name="name" value="{{ old('name', $product->name) }}" required placeholder="Contoh: Casing iPhone 15 Clear Magsafe" class="input @error('name') input-error @enderror">
                    <x-input-error for="name" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="category_id" class="label">Kategori</label>
                        <select id="category_id" name="category_id" class="input">
                            <option value="">Tanpa kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="category_id" />
                    </div>
                    <div>
                        <label for="sku" class="label">SKU <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="sku" name="sku" value="{{ old('sku', $product->sku) }}" class="input @error('sku') input-error @enderror">
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

            <div class="card space-y-5 p-6">
                <h2 class="font-bold text-stone-900">Harga & stok</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="price" class="label">Harga jual</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-stone-400">Rp</span>
                            <input id="price" name="price" inputmode="numeric" value="{{ old('price', $product->price) }}" required class="input pl-10 @error('price') input-error @enderror">
                        </div>
                        <x-input-error for="price" />
                    </div>
                    <div>
                        <label for="compare_at_price" class="label">Harga coret <span class="font-normal text-stone-400">(opsional)</span></label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-stone-400">Rp</span>
                            <input id="compare_at_price" name="compare_at_price" inputmode="numeric" value="{{ old('compare_at_price', $product->compare_at_price) }}" class="input pl-10">
                        </div>
                        <x-input-error for="compare_at_price" />
                    </div>
                    <div>
                        <label for="stock" class="label">Stok</label>
                        <input id="stock" type="number" min="0" name="stock" value="{{ old('stock', $product->stock) }}" required class="input @error('stock') input-error @enderror">
                        <x-input-error for="stock" />
                    </div>
                    <div>
                        <label for="weight" class="label">Berat (gram)</label>
                        <input id="weight" type="number" min="1" name="weight" value="{{ old('weight', $product->weight) }}" required class="input @error('weight') input-error @enderror">
                        <p class="mt-1.5 text-xs text-stone-400">Dipakai untuk menghitung ongkos kirim.</p>
                        <x-input-error for="weight" />
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="mb-4 font-bold text-stone-900">Foto produk</h2>
                <label class="group relative block cursor-pointer overflow-hidden rounded-2xl border-2 border-dashed border-stone-200 bg-stone-50 transition hover:border-brand-300 hover:bg-brand-50/40">
                    <template x-if="preview && !removeImage">
                        <img :src="preview" alt="" class="aspect-square w-full object-cover">
                    </template>
                    <div x-show="!preview || removeImage" class="flex aspect-square flex-col items-center justify-center gap-2 text-stone-400">
                        <x-icon name="photo" class="size-10" />
                        <span class="text-sm font-semibold text-stone-600">Klik untuk unggah</span>
                        <span class="text-xs">JPG/PNG/WebP, maks 2 MB</span>
                    </div>
                    <input type="file" name="image" accept="image/*" class="sr-only"
                           @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); removeImage = false }">
                </label>
                <x-input-error for="image" />
                @if ($product->image_path)
                    <label class="mt-3 flex items-center gap-2 text-sm text-stone-600">
                        <input type="checkbox" name="remove_image" value="1" x-model="removeImage" class="checkbox"> Hapus foto
                    </label>
                @endif
            </div>

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

            <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan produk' }}</button>
        </div>
    </form>
</x-layouts.admin>
