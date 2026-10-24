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
                    hint="Rasio 16:9, disarankan 2400×1350 px (min. lebar 1200 px). Taruh objek utama di sisi kanan, karena judul tampil di kiri."
                    tooltip="Foto besar paling atas beranda, tampil selebar layar di laptop/tablet dengan judul & tombol di sisi kiri."
                    :tooltip-tips="['2400×1350 px (16:9) supaya tajam di layar besar.', 'Objek utama di sepertiga kanan; sisi kiri biarkan lapang untuk judul.', 'Tanpa foto versi HP, di HP hanya bagian tengah foto yang terlihat (dipotong tegak). Unggah juga foto HP agar hasilnya maksimal di dua tampilan.', 'Kompres ke < 500 KB (WebP/JPG kualitas ±80%) supaya beranda cepat terbuka.']" />
                <x-form.image-upload name="hero_image_mobile" label="Foto versi HP" optional :current="$images['hero_image_mobile']"
                    remove-name="remove_hero_image_mobile" aspect="aspect-[9/16]" wrapper-class="max-w-52" :max-kb="4096"
                    hint="Rasio 9:16 (mis. 1080×1920 px). Judul tampil di bagian bawah. Tanpa ini, foto utama dipotong otomatis."
                    tooltip="Menggantikan foto utama di HP (lebar di bawah 768 px). Hero di HP hampir setinggi layar, jadi foto tegak terlihat jauh lebih baik."
                    :tooltip-tips="['1080×1920 px (9:16).', 'Judul & tombol tampil di ±40% bagian bawah: taruh objek utama di bagian atas atau tengah.', 'Boleh foto yang sama dengan desktop, cukup di-crop tegak.']" />
                <x-form.select name="hero_text" label="Warna teks di atas foto" :search="false" :selected="$values['hero_text'] ?? 'light'"
                    :options="['light' => 'Putih (untuk foto gelap)', 'dark' => 'Gelap (untuk foto terang)']"
                    tooltip="Warna judul & tombol di atas foto. Lapisan gradasi (gelap atau terang) otomatis ditambahkan supaya teks tetap terbaca."
                    tooltip-tips="Cek beranda di HP setelah menyimpan: bila teks sulit dibaca, ganti pilihan ini." />
            </section>

            {{-- Teks hero --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-accent-50 text-accent-600"><x-icon name="sparkles" /></span>
                    <div><h2 class="font-bold text-stone-900">Judul & tombol</h2><p class="text-xs text-stone-500">Teks yang tampil di atas foto hero.</p></div>
                </div>
                <x-form.input name="hero_eyebrow" label="Teks kecil di atas judul" :value="$values['hero_eyebrow']" maxlength="60" optional :hint="$hint('hero_eyebrow')"
                    tooltip="Label pendek berhuruf kapital di atas judul hero, mis. KOLEKSI BARU atau PROMO AKHIR BULAN." />
                <x-form.input name="hero_title" label="Judul" :value="$values['hero_title']" maxlength="90" optional :hint="$hint('hero_title')"
                    tooltip="Tulisan paling besar di beranda."
                    tooltip-tips="3–7 kata supaya tidak lebih dari 3 baris di HP." />
                <x-form.textarea name="hero_subtitle" label="Deskripsi singkat" :value="$values['hero_subtitle']" rows="2" maxlength="200" optional :hint="$hint('hero_subtitle')" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="hero_primary_label" label="Tombol utama" :value="$values['hero_primary_label']" maxlength="30" optional :hint="$hint('hero_primary_label')" />
                    <x-form.input name="hero_primary_url" label="Link tombol utama" :value="$values['hero_primary_url']" maxlength="255" optional placeholder="/produk" :hint="$hint('hero_primary_url')"
                        tooltip="Halaman yang dibuka tombol pertama. Awali / untuk halaman toko atau https:// untuk link luar." />
                    <x-form.input name="hero_secondary_label" label="Tombol kedua" :value="$values['hero_secondary_label']" maxlength="30" optional :hint="$hint('hero_secondary_label')"
                        tooltip="Tombol di samping tombol utama, untuk ajakan kedua (mis. Lihat promo). Di HP keduanya tampil berdampingan, jadi pakai teks pendek." />
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
                    hint="Satu pengumuman per baris, maks. 6. Kosongkan untuk memakai teks bawaan."
                    tooltip="Tampil di bar tipis paling atas semua halaman toko, bergantian tiap beberapa detik."
                    :tooltip-tips="['Maks. ±50 karakter per baris supaya muat satu baris di HP.', 'Isi info yang membuat orang membeli: gratis ongkir, kode voucher, jam kirim.']" />
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

    {{-- Best Seller: form terpisah supaya tidak ikut mengunggah foto hero. --}}
    <form id="best-seller" method="POST" action="{{ route('admin.storefront.best-sellers') }}" class="mt-6 scroll-mt-24"
          x-data="bestSellerPicker(@js([
              'mode' => old('best_seller_mode', $bestSeller['mode']),
              'items' => $bestSeller['products'],
              'max' => \App\Services\Storefront\BestSellers::MAX_PICKS,
              'shown' => \App\Services\Storefront\BestSellers::LIMIT,
              'searchUrl' => route('admin.storefront.products'),
          ]))">
        @csrf
        @method('PUT')

        <section class="card space-y-5 p-6">
            <div class="flex flex-wrap items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-rose-50 text-rose-600"><x-icon name="trending-up" /></span>
                <div class="min-w-0 flex-1">
                    <h2 class="flex items-center gap-1 font-bold text-stone-900">Best Seller
                        <x-tooltip label="Best Seller" text="Otomatis: produk terlaris 90 hari terakhir dari pesanan yang sudah dibayar, diperbarui tiap 30 menit. Manual: Anda pilih & urutkan sendiri."
                                   :tips="['Toko baru yang penjualannya belum banyak: pakai Manual atau tandai beberapa Produk unggulan.', 'Manual cocok untuk menonjolkan produk promo atau stok yang ingin dihabiskan.']" />
                    </h2>
                    <p class="text-xs text-stone-500">Produk di tab "Best Seller" beranda (tampil maks. {{ \App\Services\Storefront\BestSellers::LIMIT }}).</p>
                </div>
                <button class="btn btn-primary max-sm:w-full">Simpan Best Seller</button>
            </div>

            <fieldset class="grid gap-3 sm:grid-cols-2">
                <legend class="sr-only">Cara memilih produk</legend>
                <label class="flex cursor-pointer gap-3 rounded-xl border p-4 transition" :class="mode === 'auto' ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500' : 'border-stone-200 hover:border-stone-300'">
                    <input type="radio" name="best_seller_mode" value="auto" x-model="mode" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                    <span><span class="block text-sm font-semibold text-stone-900">Otomatis</span>
                        <span class="block text-xs text-stone-500">Terlaris 90 hari terakhir, dilengkapi produk unggulan lalu produk terbaru.</span></span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded-xl border p-4 transition" :class="mode === 'manual' ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500' : 'border-stone-200 hover:border-stone-300'">
                    <input type="radio" name="best_seller_mode" value="manual" x-model="mode" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                    <span><span class="block text-sm font-semibold text-stone-900">Manual</span>
                        <span class="block text-xs text-stone-500">Pilih & urutkan sendiri produknya.</span></span>
                </label>
            </fieldset>

            <div x-show="mode === 'manual'" x-cloak class="space-y-5">
                <label class="flex items-start gap-3 text-sm text-stone-700">
                    <input type="hidden" name="best_seller_fill" value="0">
                    <input type="checkbox" name="best_seller_fill" value="1" @checked(old('best_seller_fill', $bestSeller['fill'])) class="mt-0.5 rounded text-brand-600 focus:ring-brand-500">
                    <span>Lengkapi otomatis bila pilihan kurang dari {{ \App\Services\Storefront\BestSellers::LIMIT }}
                        <span class="block text-xs text-stone-500">Sisa tempat diisi produk terlaris (otomatis) setelah pilihan Anda.</span></span>
                </label>

                {{-- Pencarian produk --}}
                <div class="relative" @click.outside="open = false" @keydown.escape.window="open = false">
                    <label for="best-seller-search" class="label">Tambah produk</label>
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
                        <input id="best-seller-search" type="search" x-model="query" autocomplete="off" class="input pl-9"
                               placeholder="Cari nama atau SKU produk..." :disabled="full"
                               @focus="search()" @input.debounce.300ms="search()" @keydown.enter.prevent="results[0] && add(results[0])">
                    </div>
                    <p class="hint" x-show="full">Sudah {{ \App\Services\Storefront\BestSellers::MAX_PICKS }} produk. Hapus salah satu untuk menambah.</p>
                    <div x-show="open && !full" x-transition.opacity class="absolute inset-x-0 top-full z-20 mt-1 max-h-80 overflow-y-auto rounded-xl border border-stone-200 bg-white p-1 shadow-lg">
                        <p x-show="loading && results.length === 0" class="px-3 py-2 text-sm text-stone-500">Memuat...</p>
                        <p x-show="!loading && results.length === 0" class="px-3 py-2 text-sm text-stone-500">Produk tidak ditemukan.</p>
                        <template x-for="product in results" :key="product.id">
                            <button type="button" @click="add(product)" :disabled="has(product.id)"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left hover:bg-stone-50 disabled:cursor-default disabled:opacity-50">
                                <span class="size-10 shrink-0 overflow-hidden rounded-lg bg-stone-100">
                                    <img x-show="product.image" :src="product.image" alt="" class="size-full object-cover" loading="lazy">
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-stone-900" x-text="product.name"></span>
                                    <span class="block truncate text-xs text-stone-500"><span x-text="product.price"></span> · Stok <span x-text="product.stock"></span><span x-show="product.sku"> · <span x-text="product.sku"></span></span></span>
                                </span>
                                <span x-show="problem(product)" x-text="problem(product)" class="badge bg-stone-100 text-stone-600"></span>
                                <span x-show="has(product.id)" class="text-brand-600"><x-icon name="check" class="size-4" /></span>
                                <span x-show="!has(product.id)" class="text-stone-400"><x-icon name="plus" class="size-4" /></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Daftar pilihan --}}
                <div>
                    <div class="mb-2 flex items-center justify-between text-xs text-stone-500">
                        <span>Seret <x-icon name="grip" class="inline size-4 align-text-bottom" /><span class="max-sm:hidden"> atau pakai tombol panah</span> untuk mengubah urutan.</span>
                        <span><span x-text="items.length"></span>/{{ \App\Services\Storefront\BestSellers::MAX_PICKS }}</span>
                    </div>
                    <p x-show="items.length === 0" class="rounded-xl border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">Belum ada produk. Cari produk di atas untuk menambahkan.</p>
                    <ol class="divide-y divide-stone-100 overflow-hidden rounded-xl border border-stone-200" x-show="items.length > 0">
                        <template x-for="(product, index) in items" :key="product.id">
                            <li :data-pick="index" class="flex items-center gap-2 bg-white px-2 py-2 sm:gap-3 sm:px-3"
                                :class="{ 'bg-brand-50 ring-2 ring-inset ring-brand-400': dragging === product.id, 'opacity-60': !visibleIds.includes(product.id) }">
                                <input type="hidden" name="best_seller_products[]" x-bind:value="product.id">
                                <button type="button" class="cursor-grab touch-none rounded p-1 text-stone-400 hover:text-stone-600 active:cursor-grabbing" title="Seret untuk mengubah urutan"
                                        @pointerdown.prevent="startDrag(index, $event)" @pointermove="drag($event)" @pointerup="endDrag()" @pointercancel="endDrag()">
                                    <x-icon name="grip" class="size-5" /><span class="sr-only">Seret</span>
                                </button>
                                <span class="w-5 text-center text-xs font-semibold text-stone-400" x-text="index + 1"></span>
                                <span class="size-10 shrink-0 sm:size-11 overflow-hidden rounded-lg bg-stone-100">
                                    <img x-show="product.image" :src="product.image" alt="" class="size-full object-cover" loading="lazy">
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-stone-900" x-text="product.name"></span>
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-500">
                                        <span><span x-text="product.price"></span> · Stok <span x-text="product.stock"></span></span>
                                        <span x-show="problem(product)" class="badge whitespace-nowrap bg-amber-50 text-amber-700" title="Tidak tampil di beranda sampai produk aktif & ada stok" x-text="problem(product)"></span>
                                        <span x-show="!problem(product) && !visibleIds.includes(product.id)" class="badge whitespace-nowrap bg-stone-100 text-stone-600" title="Tampil bila ada pilihan di atasnya yang habis">Cadangan</span>
                                    </span>
                                </span>
                                <span class="flex shrink-0 items-center">
                                    <button type="button" class="rounded p-1 text-stone-400 hover:text-stone-700 disabled:opacity-30 max-sm:hidden" :disabled="index === 0" @click="move(index, -1)" title="Naikkan"><x-icon name="chevron-up" class="size-4" /><span class="sr-only">Naikkan</span></button>
                                    <button type="button" class="rounded p-1 text-stone-400 hover:text-stone-700 disabled:opacity-30 max-sm:hidden" :disabled="index === items.length - 1" @click="move(index, 1)" title="Turunkan"><x-icon name="chevron-down" class="size-4" /><span class="sr-only">Turunkan</span></button>
                                    <button type="button" class="rounded p-1 text-stone-400 hover:text-rose-600" @click="remove(index)" title="Hapus"><x-icon name="x" class="size-4" /><span class="sr-only">Hapus</span></button>
                                </span>
                            </li>
                        </template>
                    </ol>
                    <p class="hint">Produk nonaktif atau stok habis otomatis disembunyikan dari beranda, lalu tampil lagi saat stok kembali. Lebih dari {{ \App\Services\Storefront\BestSellers::LIMIT }} pilihan jadi cadangan.</p>
                    <x-input-error for="best_seller_products" />
                </div>
            </div>

            <p x-show="mode === 'auto' && items.length > 0" x-cloak class="text-xs text-stone-500">
                Daftar manual (<span x-text="items.length"></span> produk) tetap tersimpan dan dipakai lagi bila beralih ke Manual.
            </p>
        </section>
    </form>
</x-layouts.admin>
