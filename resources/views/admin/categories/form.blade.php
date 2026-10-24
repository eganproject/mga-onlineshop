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
            <x-form.select name="parent_id" label="Induk kategori" placeholder="Tidak ada (kategori utama)" :search="true"
                :options="$parents->pluck('name', 'id')->all()" :selected="$category->parent_id"
                hint="Pilih induk untuk menjadikannya subkategori, mis. Casing HP › Casing iPhone."
                tooltip="Kategori hanya 2 tingkat: kategori utama dan subkategori. Subkategori tidak bisa punya anak lagi." />
            <div>
                <div class="label-row">
                    <label for="slug" class="label">Slug URL <span class="font-normal text-stone-400">(kosongkan untuk otomatis)</span></label>
                    <x-tooltip label="Slug URL" text="Dipakai di alamat filter katalog, mis. /produk?kategori=casing-iphone. Dibuat otomatis dari nama bila kosong."
                               tips="Hindari mengubah slug yang sudah dibagikan supaya link lama tetap berfungsi." />
                </div>
                <input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" class="input @error('slug') input-error @enderror">
                <x-input-error for="slug" />
            </div>
            <x-form.textarea name="description" label="Deskripsi" optional :value="$category->description" rows="4" maxlength="1000"
                tooltip="Dipakai sebagai deskripsi halaman katalog kategori ini di hasil pencarian Google dan saat link dibagikan."
                tooltip-tips="1–2 kalimat (±150 karakter) yang menyebut jenis produk & keunggulan toko." />
        </div>

        <div class="space-y-6">
            <div class="card space-y-5 p-6">
                <label class="flex items-center justify-between gap-3">
                    <span><span class="block text-sm font-semibold text-stone-800">Tampilkan di toko</span><span class="text-xs text-stone-500">Nonaktifkan untuk menyembunyikan</span></span>
                    <input type="checkbox" name="is_active" value="1" class="checkbox size-5" @checked(old('is_active', $category->is_active))>
                </label>
                <x-form.number name="sort_order" label="Urutan tampil" :value="$category->sort_order ?? 0" min="0"
                    tooltip="Angka lebih kecil tampil lebih dulu di menu dan tab Kategori beranda. Urutan sama diurutkan menurut nama."
                    tooltip-tips="Beri jarak 10, 20, 30... supaya mudah menyisipkan kategori baru di tengah." />
                <x-form.image-upload name="image" label="Gambar" optional :current="$category->imageUrl()" remove-name="remove_image" aspect="aspect-square"
                    tooltip="Tampil sebagai kotak persegi di tab Kategori beranda, dengan nama kategori di bawahnya. Tanpa gambar, tampil logo MGA dengan nama kategori."
                    :tooltip-tips="['Rasio 1:1, min. 600×600 px.', 'Satu objek di tengah dengan latar polos; tidak perlu menulis nama kategori di gambar.', 'Pakai gaya foto yang seragam antar kategori supaya tab terlihat rapi.']" />
            </div>
            <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan kategori' }}</button>
        </div>
    </form>
</x-layouts.admin>
