@php
    $editing = $banner->exists;
    $dt = fn ($field) => old($field, $banner->{$field}?->format('Y-m-d\TH:i'));
    $file = 'block w-full text-sm text-stone-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100';
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
                <div>
                    <label for="image" class="label">Gambar banner</label>
                    @if ($editing)
                        <img src="{{ $banner->imageUrl() }}" alt="" class="mb-3 aspect-[21/9] w-full rounded-xl object-cover">
                    @endif
                    <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" @required(! $editing) class="{{ $file }}">
                    <p class="mt-1.5 text-xs text-stone-400">Disarankan 1920×820 px (rasio ±21:9), JPG/PNG/WebP maks. 4 MB.{{ $editing ? ' Kosongkan bila tidak diganti.' : '' }}</p>
                    <x-input-error for="image" />
                </div>
                <div>
                    <label for="mobile_image" class="label">Gambar versi HP <span class="font-normal text-stone-400">(opsional)</span></label>
                    @if ($banner->mobileImageUrl())
                        <img src="{{ $banner->mobileImageUrl() }}" alt="" class="mb-3 aspect-[4/5] w-40 rounded-xl object-cover">
                        <label class="mb-3 flex items-center gap-2 text-sm text-stone-600">
                            <input type="checkbox" name="remove_mobile_image" value="1" class="checkbox"> Hapus gambar HP
                        </label>
                    @endif
                    <input id="mobile_image" type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp" class="{{ $file }}">
                    <p class="mt-1.5 text-xs text-stone-400">Rasio 4:5 (mis. 1080×1350 px). Tanpa ini, gambar utama dipotong otomatis di HP.</p>
                    <x-input-error for="mobile_image" />
                </div>
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
                <div>
                    <label for="sort_order" class="label">Urutan tampil</label>
                    <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $banner->sort_order ?? 0) }}" class="input">
                    <x-input-error for="sort_order" />
                </div>
                <div>
                    <label for="starts_at" class="label">Mulai tayang <span class="font-normal text-stone-400">(opsional)</span></label>
                    <input id="starts_at" type="datetime-local" name="starts_at" value="{{ $dt('starts_at') }}" class="input @error('starts_at') input-error @enderror">
                    <x-input-error for="starts_at" />
                </div>
                <div>
                    <label for="ends_at" class="label">Berakhir <span class="font-normal text-stone-400">(opsional)</span></label>
                    <input id="ends_at" type="datetime-local" name="ends_at" value="{{ $dt('ends_at') }}" class="input @error('ends_at') input-error @enderror">
                    <x-input-error for="ends_at" />
                </div>
            </div>
            <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan banner' }}</button>
        </div>
    </form>
</x-layouts.admin>
