@php
    $editing = $banner->exists;
@endphp
<x-layouts.admin :title="$editing ? 'Edit Banner' : 'Tambah Banner'">
    <x-admin.page-header :title="$editing ? 'Edit banner' : 'Tambah banner'" :back="route('admin.banners.index')" />

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.banners.update', $banner) : route('admin.banners.store') }}"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <div class="card space-y-5 p-6">
                <x-form.image-upload name="image" label="Gambar banner" :current="$editing ? $banner->imageUrl() : null"
                    aspect="aspect-[21/9]" :max-kb="4096" :required="! $editing"
                    :hint="'Disarankan 1920×820 px (rasio ±21:9), JPG/PNG/WebP maks. 4 MB.'.($editing ? ' Kosongkan bila tidak diganti.' : '')" />
                <x-form.image-upload name="mobile_image" label="Gambar versi HP" optional :current="$banner->mobileImageUrl()"
                    remove-name="remove_mobile_image" aspect="aspect-[4/5]" wrapper-class="max-w-56" :max-kb="4096"
                    hint="Rasio 4:5 (mis. 1080×1350 px). Tanpa ini, gambar utama dipotong otomatis di HP." />
            </div>

            <div class="card space-y-5 p-6">
                <div>
                    <label for="title" class="label">Judul</label>
                    <input id="title" name="title" value="{{ old('title', $banner->title) }}" required maxlength="120" placeholder="Diskon 10% semua casing" class="input @error('title') input-error @enderror">
                    <p class="mt-1.5 text-xs text-stone-400">Juga dipakai sebagai teks alternatif gambar.</p>
                    <x-input-error for="title" />
                </div>
                <div>
                    <label for="subtitle" class="label">Subjudul <span class="font-normal text-stone-400">(opsional)</span></label>
                    <input id="subtitle" name="subtitle" value="{{ old('subtitle', $banner->subtitle) }}" maxlength="255" placeholder="Pakai kode HEMAT10 sampai akhir bulan" class="input">
                    <x-input-error for="subtitle" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="link_url" class="label">Link <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="link_url" name="link_url" value="{{ old('link_url', $banner->link_url) }}" maxlength="500" placeholder="/produk?promo=1" class="input @error('link_url') input-error @enderror">
                        <x-input-error for="link_url" />
                    </div>
                    <div>
                        <label for="button_label" class="label">Teks tombol <span class="font-normal text-stone-400">(opsional)</span></label>
                        <input id="button_label" name="button_label" value="{{ old('button_label', $banner->button_label) }}" maxlength="40" placeholder="Belanja sekarang" class="input">
                        <x-input-error for="button_label" />
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card space-y-5 p-6">
                <label class="flex items-center justify-between gap-3">
                    <span><span class="block text-sm font-semibold text-stone-800">Tampilkan di beranda</span><span class="text-xs text-stone-500">Nonaktifkan untuk menyembunyikan</span></span>
                    <input type="checkbox" name="is_active" value="1" class="checkbox size-5" @checked(old('is_active', $banner->is_active))>
                </label>
                <label class="flex items-center justify-between gap-3">
                    <span><span class="block text-sm font-semibold text-stone-800">Tampilkan teks di atas gambar</span><span class="text-xs text-stone-500">Matikan bila tulisan sudah ada di gambar</span></span>
                    <input type="checkbox" name="show_text" value="1" class="checkbox size-5" @checked(old('show_text', $banner->show_text))>
                </label>
                <x-form.number name="sort_order" label="Urutan tampil" :value="$banner->sort_order ?? 0" min="0" />
                <x-form.date name="starts_at" label="Mulai tayang" optional time :value="$banner->starts_at" />
                <x-form.date name="ends_at" label="Berakhir" optional time :value="$banner->ends_at" />
            </div>
            <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan banner' }}</button>
        </div>
    </form>
</x-layouts.admin>
