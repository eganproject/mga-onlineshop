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
                    :hint="'Disarankan 1920×820 px (rasio ±21:9), JPG/PNG/WebP maks. 4 MB.'.($editing ? ' Kosongkan bila tidak diganti.' : '')"
                    tooltip="Tampil di slider promo beranda, di bawah tab Best Seller/Kategori. Di layar lebar tampil 21:9 selebar halaman."
                    :tooltip-tips="['Ukuran 1920×820 px supaya tajam di layar besar.', 'Tanpa gambar versi HP, banner dipotong jadi 4:3 di HP: hanya ±55% bagian tengah yang terlihat. Taruh objek & tulisan penting di tengah.', 'Bila teks di atas gambar aktif, sisakan area agak gelap/kosong di sisi kiri untuk judul.', 'Kompres ke < 500 KB (mis. WebP) supaya beranda cepat terbuka.']" />
                <x-form.image-upload name="mobile_image" label="Gambar versi HP" optional :current="$banner->mobileImageUrl()"
                    remove-name="remove_mobile_image" aspect="aspect-[4/5]" wrapper-class="max-w-56" :max-kb="4096"
                    hint="Rasio 4:5 (mis. 1080×1350 px). Tanpa ini, gambar utama dipotong otomatis di HP."
                    tooltip="Menggantikan gambar utama di layar HP (lebar di bawah 640 px), jadi desain bisa dibuat tegak dan tulisan tetap besar."
                    :tooltip-tips="['Rasio 4:5, mis. 1080×1350 px.', 'Bila teks di atas gambar aktif, judul tampil di ±40% bagian bawah: taruh objek di bagian atas.']" />
            </div>

            <div class="card space-y-5 p-6">
                <div>
                    <label for="title" class="label">Judul</label>
                    <input id="title" name="title" value="{{ old('title', $banner->title) }}" required maxlength="120" placeholder="Diskon 10% semua frame akrilik" class="input @error('title') input-error @enderror">
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
                        <div class="label-row">
                            <label for="link_url" class="label">Link <span class="font-normal text-stone-400">(opsional)</span></label>
                            <x-tooltip label="Link banner" text="Halaman yang dibuka saat banner diklik. Awali / untuk halaman toko (mis. /produk?kategori=frame-akrilik) atau https:// untuk link luar (dibuka di tab baru)." />
                        </div>
                        <input id="link_url" name="link_url" value="{{ old('link_url', $banner->link_url) }}" maxlength="500" placeholder="/produk?promo=1" class="input @error('link_url') input-error @enderror">
                        <x-input-error for="link_url" />
                    </div>
                    <div>
                        <div class="label-row">
                            <label for="button_label" class="label">Teks tombol <span class="font-normal text-stone-400">(opsional)</span></label>
                            <x-tooltip label="Teks tombol" text="Tombol putih di bawah judul banner. Hanya tampil bila Link terisi dan teks di atas gambar aktif."
                                       tips="Singkat & ajak bertindak, mis. “Belanja sekarang” atau “Klaim voucher”." />
                        </div>
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
                    <span><span class="flex items-center gap-1 text-sm font-semibold text-stone-800">Tampilkan teks di atas gambar <x-tooltip label="Tampilkan teks di atas gambar" text="Judul, subjudul, dan tombol ditulis di atas gambar dengan lapisan gelap tipis supaya terbaca. Matikan bila desain gambar sudah berisi tulisan." /></span><span class="text-xs text-stone-500">Matikan bila tulisan sudah ada di gambar</span></span>
                    <input type="checkbox" name="show_text" value="1" class="checkbox size-5" @checked(old('show_text', $banner->show_text))>
                </label>
                <x-form.number name="sort_order" label="Urutan tampil" :value="$banner->sort_order ?? 0" min="0"
                    tooltip="Angka lebih kecil tampil lebih dulu di slider. Banner pertama juga dimuat paling cepat, jadi taruh promo terpenting di urutan 0." />
                <x-form.date name="starts_at" label="Mulai tayang" optional time :value="$banner->starts_at"
                    tooltip="Banner tampil dan hilang otomatis sesuai jadwal ini (selama aktif). Kosongkan untuk langsung/selalu tampil." />
                <x-form.date name="ends_at" label="Berakhir" optional time :value="$banner->ends_at" />
            </div>
            <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan perubahan' : 'Simpan banner' }}</button>
        </div>
    </form>
</x-layouts.admin>
