<x-layouts.shop>
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-50 via-cream to-amber-50"></div>
        <div class="absolute -right-24 -top-24 size-96 rounded-full bg-brand-200/40 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-12 sm:px-6 md:grid-cols-2 md:py-20 lg:px-8">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-700 shadow-sm ring-1 ring-brand-100">
                    <x-icon name="sparkles" class="size-4" /> Koleksi terbaru sudah tersedia
                </span>
                <h1 class="mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight text-stone-900 sm:text-5xl lg:text-6xl">
                    Aksesoris gadget <span class="bg-gradient-to-r from-brand-600 to-amber-500 bg-clip-text text-transparent">original</span>, harga gudang.
                </h1>
                <p class="mt-5 max-w-lg text-base leading-relaxed text-stone-600 sm:text-lg">{{ config('shop.tagline') }}. Lengkapi gadget kesayanganmu dengan casing, charger, kabel, dan audio berkualitas.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('products.index') }}" class="btn btn-primary px-6 py-3 text-base">Belanja sekarang <x-icon name="arrow-right" class="size-4" /></a>
                    @guest
                        <a href="{{ route('register') }}" class="btn btn-secondary px-6 py-3 text-base">Daftar gratis</a>
                    @endguest
                </div>
                <dl class="mt-10 grid max-w-md grid-cols-3 gap-4">
                    @foreach ([['100%', 'Original'], ['24 jam', 'Proses kirim'], ['5+', 'Pilihan kurir']] as [$value, $label])
                        <div><dt class="text-2xl font-extrabold text-stone-900">{{ $value }}</dt><dd class="text-xs text-stone-500">{{ $label }}</dd></div>
                    @endforeach
                </dl>
            </div>
            <div class="relative hidden md:block">
                <div class="grid grid-cols-2 gap-4">
                    @foreach ($featured->take(4) as $i => $product)
                        <a href="{{ route('products.show', $product) }}" @class(['group block overflow-hidden rounded-3xl bg-white p-2 shadow-xl shadow-stone-900/5 ring-1 ring-stone-200/60 transition hover:-translate-y-1', 'translate-y-8' => $i % 2 === 1])>
                            <x-product-image :product="$product" class="aspect-square rounded-2xl" />
                            <div class="p-3">
                                <p class="truncate text-sm font-semibold text-stone-800">{{ $product->name }}</p>
                                <p class="text-sm font-bold text-brand-600">@rupiah($product->price)</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Keunggulan --}}
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="-mt-2 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ([['shield', 'Produk original', 'Garansi resmi & bergaransi toko'], ['truck', 'Kirim se-Indonesia', 'JNE, SiCepat, J&T, POS'], ['credit-card', 'Bayar mudah', 'Transfer, e-wallet, QRIS'], ['chat', 'CS ramah', 'Siap bantu tiap hari']] as [$icon, $title, $desc])
                <div class="card flex items-start gap-3 p-4">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$icon" /></span>
                    <div><p class="text-sm font-bold text-stone-900">{{ $title }}</p><p class="text-xs text-stone-500">{{ $desc }}</p></div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Kategori --}}
    @if ($categories->isNotEmpty())
        <section class="mx-auto mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-stone-900">Belanja per kategori</h2>
                    <p class="mt-1 text-sm text-stone-500">Temukan yang kamu cari lebih cepat.</p>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', ['kategori' => $category->slug]) }}" class="group flex flex-col items-center gap-3 rounded-2xl border border-stone-200/70 bg-white p-4 text-center transition hover:border-brand-200 hover:bg-brand-50/50">
                        <span class="grid size-14 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-brand-100 to-amber-100 text-brand-600 transition group-hover:scale-105">
                            @if ($category->imageUrl())
                                <img src="{{ $category->imageUrl() }}" alt="" class="size-full object-cover">
                            @else
                                <x-icon name="tag" class="size-6" />
                            @endif
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-stone-800">{{ $category->name }}</span>
                            <span class="block text-xs text-stone-500">{{ $category->products_count }} produk</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Unggulan --}}
    @if ($featured->isNotEmpty())
        <section class="mx-auto mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-stone-900">Produk unggulan</h2>
                    <p class="mt-1 text-sm text-stone-500">Pilihan favorit pelanggan MGA.</p>
                </div>
                <a href="{{ route('products.index') }}" class="hidden text-sm font-semibold text-brand-600 hover:text-brand-700 sm:inline-flex sm:items-center sm:gap-1">Lihat semua <x-icon name="arrow-right" class="size-4" /></a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($featured as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Banner --}}
    <section class="mx-auto mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-stone-900 px-6 py-10 sm:px-12 sm:py-14">
            <div class="absolute -right-10 -top-10 size-64 rounded-full bg-brand-500/30 blur-3xl"></div>
            <div class="relative flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
                <div>
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">Belanja grosir untuk tokomu?</h2>
                    <p class="mt-2 max-w-xl text-stone-300">Dapatkan harga spesial untuk pembelian dalam jumlah banyak. Hubungi tim kami untuk penawaran.</p>
                </div>
                <a href="{{ config('shop.whatsapp') ? 'https://wa.me/'.config('shop.whatsapp') : route('products.index') }}" class="btn btn-primary shrink-0 px-6 py-3">Hubungi kami</a>
            </div>
        </div>
    </section>

    {{-- Terbaru --}}
    @if ($latest->isNotEmpty())
        <section class="mx-auto mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-extrabold tracking-tight text-stone-900">Baru masuk</h2>
            <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($latest as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.shop>
