@php($editing = $category->exists)
<x-layouts.admin :title="$editing ? 'Edit Kategori' : 'Tambah Kategori'">
    <x-admin.page-header :title="$editing ? 'Edit kategori' : 'Tambah kategori'" :back="route('admin.categories.index')" />

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card space-y-5 p-6 lg:col-span-2">
            <div>
                <label for="name" class="label">Nama kategori</label>
                <input id="name" name="name" value="{{ old('name', $category->name) }}" required class="input @error('name') input-error @enderror">
                <x-input-error for="name" />
            </div>
            <div>
                <label for="slug" class="label">Slug URL <span class="font-normal text-stone-400">(kosongkan untuk otomatis)</span></label>
                <input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" class="input @error('slug') input-error @enderror">
                <x-input-error for="slug" />
            </div>
            <div>
                <label for="description" class="label">Deskripsi</label>
                <textarea id="description" name="description" rows="4" class="input">{{ old('description', $category->description) }}</textarea>
                <x-input-error for="description" />
            </div>
        </div>

        <div class="space-y-6">
            <div class="card space-y-5 p-6">
                <label class="flex items-center justify-between gap-3">
                    <span><span class="block text-sm font-semibold text-stone-800">Tampilkan di toko</span><span class="text-xs text-stone-500">Nonaktifkan untuk menyembunyikan</span></span>
                    <input type="checkbox" name="is_active" value="1" class="checkbox size-5" @checked(old('is_active', $category->is_active))>
                </label>
                <div>
                    <label for="sort_order" class="label">Urutan tampil</label>
                    <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="input">
                </div>
                <div>
                    <label for="image" class="label">Gambar</label>
                    @if ($category->imageUrl())
                        <img src="{{ $category->imageUrl() }}" alt="" class="mb-3 aspect-video w-full rounded-xl object-cover">
                    @endif
                    <input id="image" type="file" name="image" accept="image/*" class="block w-full text-sm text-stone-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                    <x-input-error for="image" />
                </div>
            </div>
            <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan kategori' }}</button>
        </div>
    </form>
</x-layouts.admin>
