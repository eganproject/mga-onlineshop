@php($editing = $category->exists)
<x-layouts.admin :title="$editing ? 'Edit Kategori' : 'Tambah Kategori'">
    <x-admin.page-header :title="$editing ? 'Edit kategori' : 'Tambah kategori'" :back="route('admin.categories.index')" />

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
          class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6 lg:col-span-2">
        <div class="card space-y-5 p-6">
            <div>
                <label for="name" class="label">Nama kategori</label>
                <input id="name" name="name" value="{{ old('name', $category->name) }}" required class="input @error('name') input-error @enderror">
                <x-input-error for="name" />
            </div>
            <x-form.select name="parent_id" label="Induk kategori" placeholder="Tidak ada (kategori utama)" :search="true"
                :options="$parents->pluck('name', 'id')->all()" :selected="$category->parent_id"
                hint="Pilih induk untuk menjadikannya subkategori, mis. Tempat Brosur › Akrilik Pocket."
                tooltip="Kategori hanya 2 tingkat: kategori utama dan subkategori. Subkategori tidak bisa punya anak lagi." />
            <div>
                <div class="label-row">
                    <label for="slug" class="label">Slug URL <span class="font-normal text-stone-400">(kosongkan untuk otomatis)</span></label>
                    <x-tooltip label="Slug URL" text="Dipakai di alamat filter katalog, mis. /produk?kategori=akrilik-pocket. Dibuat otomatis dari nama bila kosong."
                               tips="Hindari mengubah slug yang sudah dibagikan supaya link lama tetap berfungsi." />
                </div>
                <input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" class="input @error('slug') input-error @enderror">
                <x-input-error for="slug" />
            </div>
            <x-form.textarea name="description" label="Deskripsi" optional :value="$category->description" rows="4" maxlength="1000"
                hint="Tampil sebagai teks pengantar di atas daftar produk kategori ini."
                tooltip="Teks pengantar di halaman kategori. Bila Deskripsi SEO di bawah kosong, kalimat awalnya juga dipakai di hasil pencarian Google."
                :tooltip-tips="['2–3 kalimat yang menyebut jenis produk, kegunaan, dan keunggulan toko.', 'Tulis untuk pembeli, bukan deretan kata kunci.']" />
        </div>

        <x-admin.seo-fields :model="$category"
            :url="url('/produk').'?kategori='.($category->slug ?: 'nama-kategori')"
            :title-fallback="$category->name ?: 'Nama kategori'"
            :description-fallback="$editing ? $category->metaDescription() : 'Kosong = memakai deskripsi kategori.'"
            :title-tips="['Pakai kata yang dicari pembeli, mis. “Tripod Banner & X Banner Murah”.']" />
        </div>

        <div class="space-y-6">
            <div class="card space-y-5 p-6">
                <x-form.switch name="is_active" label="Tampilkan di toko" description="Matikan untuk menyembunyikan kategori dari menu toko." :checked="$category->is_active" />
                <x-form.number name="sort_order" label="Urutan tampil" :value="$category->sort_order ?? 0" min="0"
                    tooltip="Angka lebih kecil tampil lebih dulu di menu dan tab Kategori beranda. Urutan sama diurutkan menurut nama."
                    tooltip-tips="Beri jarak 10, 20, 30... supaya mudah menyisipkan kategori baru di tengah." />
                <x-form.image-upload name="image" label="Gambar" optional :current="$category->imageUrl()" remove-name="remove_image" aspect="aspect-square"
                    tooltip="Tampil sebagai kotak persegi di tab Kategori beranda, dengan nama kategori di bawahnya. Tanpa gambar, tampil logo MGA dengan nama kategori."
                    :tooltip-tips="['Rasio 1:1, min. 600×600 px.', 'Satu objek di tengah dengan latar polos; tidak perlu menulis nama kategori di gambar.', 'Pakai gaya foto yang seragam antar kategori supaya tab terlihat rapi.']" />
            </div>
            <x-admin.save-bar>
                <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan kategori' }}</button>
            </x-admin.save-bar>
        </div>
    </form>
</x-layouts.admin>
