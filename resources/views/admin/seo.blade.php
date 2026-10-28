@php
    $val = fn (string $field) => old($field, $values[$field] ?? null);
    $envHint = fn (string $field) => filled($defaults[$field] ?? null) ? 'Kosongkan untuk memakai nilai .env: '.$defaults[$field] : null;
    $homeFallback = trim(config('shop.full_name').(config('shop.tagline') ? ' – '.config('shop.tagline') : ''));
    $issues = [
        ['Produk aktif tanpa deskripsi', $health['no_description'], 'Google butuh teks untuk memahami produk. Tulis spesifikasi, ukuran, bahan, dan kegunaannya.'],
        ['Produk aktif tanpa foto', $health['no_image'], 'Produk tanpa foto jarang tampil di Google Gambar dan terlihat kosong saat dibagikan.'],
        ['Kategori tanpa deskripsi', $health['categories_no_description'], 'Teks pengantar kategori membantu halaman kategori muncul untuk pencarian umum, mis. “tripod banner”.'],
    ];
@endphp
<x-layouts.admin title="SEO">
    <x-admin.page-header title="SEO" description="Tampilan toko di hasil pencarian Google, verifikasi Search Console, dan Google Analytics." />

    <form method="POST" action="{{ route('admin.seo.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Beranda --}}
            <section class="card space-y-5 p-6" x-data="{ homeTitle: @js((string) $val('home_title')), description: @js((string) $val('description')) }">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="search" /></span>
                    <div><h2 class="font-bold text-stone-900">Beranda di Google</h2><p class="text-xs text-stone-500">Judul & deskripsi saat orang mencari nama toko.</p></div>
                </div>
                <div class="rounded-xl border border-stone-200 bg-white p-4" aria-hidden="true">
                    <p class="truncate text-xs text-stone-500">{{ str_replace(['https://', 'http://'], '', url('/')) }}</p>
                    <p class="mt-1 truncate text-lg leading-snug text-[#1a0dab]" x-text="homeTitle || @js($homeFallback)"></p>
                    <p class="mt-1 line-clamp-2 text-sm text-stone-600" x-text="description || @js((string) ($defaults['description'] ?? ''))"></p>
                </div>
                <x-form.input name="home_title" label="Judul beranda" optional maxlength="70" x-model="homeTitle"
                    :hint="'Kosong = “'.$homeFallback.'”. Idealnya 50–60 karakter.'"
                    tooltip="Judul biru beranda di hasil Google dan judul tab browser. Halaman lain tetap memakai judulnya sendiri + nama toko."
                    :tooltip-tips="['Gabungkan nama toko + yang dijual, mis. “MGA – Grosir Tripod Banner & Display Akrilik”.', 'Jangan menumpuk kata kunci; Google bisa mengganti judul yang terlihat spam.']" />
                <x-form.textarea name="description" label="Deskripsi toko" optional maxlength="160" rows="3" x-model="description"
                    hint="Dipakai di beranda dan halaman yang tidak punya deskripsi sendiri. Kosong = deskripsi bawaan (lihat pratinjau). Idealnya 120–160 karakter."
                    tooltip="Teks abu-abu di bawah judul pada hasil Google, juga saat link toko dibagikan di WhatsApp/Facebook."
                    :tooltip-tips="['Sebut produk utama, keunggulan (harga gudang, grosir), dan jangkauan kirim.']" />
            </section>

            {{-- Gambar bagikan --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-600"><x-icon name="share" /></span>
                    <div><h2 class="font-bold text-stone-900">Gambar saat link dibagikan</h2><p class="text-xs text-stone-500">Untuk halaman tanpa foto sendiri (beranda, katalog, kategori).</p></div>
                </div>
                <x-form.image-upload name="default_image" label="Gambar bagikan bawaan" optional :current="$image" :remove-name="$customImage ? 'remove_default_image' : null" aspect="aspect-[1200/630]"
                    hint="Kosong = gambar logo MGA bawaan."
                    tooltip="Tampil sebagai pratinjau besar saat link toko dibagikan di WhatsApp, Facebook, X, dan LinkedIn. Halaman produk & artikel memakai fotonya sendiri."
                    :tooltip-tips="['Ukuran ideal 1200×630 px (rasio 1,91:1), sama untuk desktop & HP.', 'Taruh logo & teks penting di tengah; WhatsApp di HP bisa memotong pinggirnya jadi persegi.', 'JPG/PNG/WebP, maks. 2 MB.']" />
            </section>

            {{-- Verifikasi & analitik --}}
            <section class="card space-y-5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="chart" /></span>
                    <div><h2 class="font-bold text-stone-900">Verifikasi & analitik</h2><p class="text-xs text-stone-500">Hubungkan toko ke Google Search Console, Bing, dan Google Analytics.</p></div>
                </div>
                <x-form.input name="google_verification" label="Kode verifikasi Google Search Console" optional :value="$val('google_verification')" maxlength="300"
                    placeholder="Tempel tag meta dari Search Console"
                    :hint="$envHint('google_verification') ?? 'Tempel tag meta utuh atau isi content-nya saja.'"
                    tooltip="Bukti bahwa situs ini milik Anda. Setelah terverifikasi, Search Console menampilkan kata kunci pencarian, halaman yang terindeks, dan error dari Google."
                    :tooltip-tips="['Di Search Console pilih “Awalan URL”, masukkan alamat toko, lalu metode “Tag HTML”.', 'Setelah disimpan, klik Verifikasi di Search Console lalu kirim alamat sitemap.xml.']" />
                <x-form.input name="bing_verification" label="Kode verifikasi Bing Webmaster" optional :value="$val('bing_verification')" maxlength="300"
                    placeholder="Tempel tag meta dari Bing"
                    :hint="$envHint('bing_verification') ?? 'Tempel tag meta utuh atau isi content-nya saja.'"
                    tooltip="Sama seperti Search Console, untuk mesin pencari Bing (juga dipakai DuckDuckGo & Yahoo)."
                    tooltip-tips="Bing bisa mengimpor situs langsung dari Search Console, jadi kolom ini boleh dikosongkan." />
                <x-form.input name="analytics_id" label="ID Google Analytics 4" optional :value="$val('analytics_id')" maxlength="30" placeholder="G-XXXXXXXXXX"
                    :hint="$envHint('analytics_id') ?? 'Kosong = Google Analytics tidak dipasang.'"
                    tooltip="Measurement ID dari Google Analytics (Admin › Aliran data › Web). Dipakai untuk melihat jumlah pengunjung, sumber kunjungan, dan halaman terpopuler. Tidak dimuat di panel admin."
                    tooltip-tips="Pakai ID yang berawalan G-, bukan UA- (versi lama yang sudah dihentikan Google)." />
            </section>
        </div>

        <div class="space-y-6">
            {{-- Status pengindeksan --}}
            <section @class(['card space-y-3 p-6', 'ring-1 ring-amber-200' => ! $indexing])>
                <div class="flex items-center gap-2">
                    <span @class(['size-2.5 rounded-full', 'bg-emerald-500' => $indexing, 'bg-amber-500' => ! $indexing])></span>
                    <h2 class="font-bold text-stone-900">{{ $indexing ? 'Terbuka untuk Google' : 'Tertutup untuk Google' }}</h2>
                </div>
                @if ($indexing)
                    <p class="text-sm text-stone-600">Halaman toko boleh diindeks. Halaman akun, keranjang, checkout, admin, serta hasil filter/pencarian tetap tidak diindeks.</p>
                @else
                    <p class="text-sm text-stone-600">Situs sedang mode demo/staging: semua halaman diberi <code class="text-xs">noindex</code> dan robots.txt menolak semua mesin pencari.</p>
                    <p class="text-xs text-stone-500">Saat siap jualan, ubah <code>SEO_INDEXING=true</code> di file <code>.env</code> server, lalu jalankan <code>php artisan config:cache</code>.</p>
                @endif
                <div class="flex flex-wrap gap-2 pt-1">
                    <a href="{{ route('sitemap') }}" target="_blank" class="btn btn-secondary px-3 py-1.5 text-xs"><x-icon name="external" class="size-3.5" /> sitemap.xml</a>
                    <a href="{{ route('robots') }}" target="_blank" class="btn btn-secondary px-3 py-1.5 text-xs"><x-icon name="external" class="size-3.5" /> robots.txt</a>
                </div>
            </section>

            {{-- Kesehatan konten --}}
            <section class="card space-y-4 p-6">
                <div class="label-row">
                    <h2 class="font-bold text-stone-900">Kelengkapan konten</h2>
                    <x-tooltip label="Kelengkapan konten" text="Hal yang paling memengaruhi peringkat toko online: setiap produk punya foto, deskripsi yang jelas, dan setiap kategori punya teks pengantar." />
                </div>
                <ul class="space-y-3">
                    @foreach ($issues as [$label, $count, $why])
                        <li class="flex gap-3">
                            <x-icon :name="$count ? 'warning' : 'check-circle'" :class="'mt-0.5 size-4 shrink-0 '.($count ? 'text-amber-500' : 'text-emerald-500')" />
                            <div class="min-w-0 text-sm">
                                <p class="font-semibold text-stone-800">{{ $label }}: {{ number_format($count, 0, ',', '.') }}</p>
                                @if ($count)<p class="text-xs text-stone-500">{{ $why }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="text-xs text-stone-500">Judul & deskripsi SEO per produk, kategori, dan artikel bisa diatur di form edit masing-masing (bagian “SEO”).</p>
                @can('catalog.manage')
                    <a href="{{ route('admin.products.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Buka daftar produk</a>
                @endcan
            </section>

            <div class="card sticky top-20 p-4">
                <button class="btn btn-primary w-full">Simpan pengaturan SEO</button>
            </div>
        </div>
    </form>
</x-layouts.admin>
