@php
    $hint = fn (string $field) => filled($defaults[$field] ?? null) ? 'Kosongkan untuk memakai teks bawaan: "'.$defaults[$field].'"' : null;
@endphp
<x-layouts.admin title="Tampilan Beranda">
    <x-admin.page-header title="Tampilan Beranda" description="Foto besar (hero) di bagian atas beranda dan pengumuman di bar paling atas toko.">
        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="external" class="size-4" /> Lihat beranda</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.storefront.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="space-y-6 lg:col-span-2">
            {{-- Foto hero --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="photo" /></span>
                    <div><h2 class="font-bold text-stone-900">Foto hero</h2><p class="text-xs text-stone-500">Tampil selebar layar. Tanpa foto, beranda memakai ilustrasi bawaan MGA.</p></div>
                </div>
                <x-form.image-upload name="hero_image" label="Foto utama (desktop)" optional :current="$images['hero_image']"
                    remove-name="remove_hero_image" aspect="aspect-video" :max-kb="5120"
                    hint="Rasio 16:9, disarankan 2400×1350 px (min. lebar 1200 px). Taruh objek utama di sisi kanan, karena judul tampil di kiri." />
                <x-form.image-upload name="hero_image_mobile" label="Foto versi HP" optional :current="$images['hero_image_mobile']"
                    remove-name="remove_hero_image_mobile" aspect="aspect-[9/16]" wrapper-class="max-w-52" :max-kb="4096"
                    hint="Rasio 9:16 (mis. 1080×1920 px). Judul tampil di bagian bawah. Tanpa ini, foto utama dipotong otomatis." />
                <x-form.select name="hero_text" label="Warna teks di atas foto" :search="false" :selected="$values['hero_text'] ?? 'light'"
                    :options="['light' => 'Putih (untuk foto gelap)', 'dark' => 'Gelap (untuk foto terang)']" />
            </section>

            {{-- Teks hero --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-accent-50 text-accent-600"><x-icon name="sparkles" /></span>
                    <div><h2 class="font-bold text-stone-900">Judul & tombol</h2><p class="text-xs text-stone-500">Teks yang tampil di atas foto hero.</p></div>
                </div>
                <x-form.input name="hero_eyebrow" label="Teks kecil di atas judul" :value="$values['hero_eyebrow']" maxlength="60" optional :hint="$hint('hero_eyebrow')" />
                <x-form.input name="hero_title" label="Judul" :value="$values['hero_title']" maxlength="90" optional :hint="$hint('hero_title')" />
                <x-form.textarea name="hero_subtitle" label="Deskripsi singkat" :value="$values['hero_subtitle']" rows="2" maxlength="200" optional :hint="$hint('hero_subtitle')" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="hero_primary_label" label="Tombol utama" :value="$values['hero_primary_label']" maxlength="30" optional :hint="$hint('hero_primary_label')" />
                    <x-form.input name="hero_primary_url" label="Link tombol utama" :value="$values['hero_primary_url']" maxlength="255" optional placeholder="/produk" :hint="$hint('hero_primary_url')" />
                    <x-form.input name="hero_secondary_label" label="Tombol kedua" :value="$values['hero_secondary_label']" maxlength="30" optional :hint="$hint('hero_secondary_label')" />
                    <x-form.input name="hero_secondary_url" label="Link tombol kedua" :value="$values['hero_secondary_url']" maxlength="255" optional placeholder="/produk?promo=1" :hint="$hint('hero_secondary_url')" />
                </div>
            </section>
        </div>

        <div class="space-y-6">
            {{-- Topbar --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-600"><x-icon name="megaphone" /></span>
                    <div><h2 class="font-bold text-stone-900">Pengumuman topbar</h2><p class="text-xs text-stone-500">Bergantian tiap beberapa detik di bar paling atas.</p></div>
                </div>
                <x-form.textarea name="announcements" label="Pengumuman" :value="$values['announcements']" rows="5" maxlength="1000" optional
                    hint="Satu pengumuman per baris, maks. 6. Kosongkan untuk memakai teks bawaan." />
            </section>

            <div class="flex gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                <x-icon name="info" class="size-5 shrink-0" />
                <p>Link diawali <code class="font-mono">/</code> untuk halaman toko, mis. <code class="font-mono">/produk?kategori=casing</code>, atau <code class="font-mono">https://</code> untuk link luar.</p>
            </div>

            <div class="card sticky top-20 p-4">
                <button class="btn btn-primary w-full">Simpan tampilan</button>
            </div>
        </div>
    </form>
</x-layouts.admin>
