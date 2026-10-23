@php
    $heroImage = config('shop.storefront.hero_image');
    if ($heroImage && ! str_starts_with($heroImage, 'http')) {
        $heroImage = asset($heroImage);
    }
    $heroProducts = $featured->isNotEmpty() ? $featured : $latest;
@endphp
<x-layouts.shop>
    {{-- Hero --}}
    @if ($heroImage)
        <section class="relative isolate flex min-h-[78vh] items-end overflow-hidden bg-ink text-white">
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" fetchpriority="high">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
            <div class="shop-container pb-12 sm:pb-16 lg:pb-20">
                <p class="eyebrow text-white/80">Koleksi terbaru</p>
                <h1 class="display mt-3 max-w-4xl text-5xl leading-[0.9] sm:text-7xl lg:text-8xl">Aksesoris original, harga gudang.</h1>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('products.index') }}" class="btn btn-light btn-pill">Belanja sekarang</a>
                    <a href="{{ route('products.index', ['urut' => 'terbaru']) }}" class="btn btn-pill bg-transparent text-white ring-1 ring-white ring-inset hover:bg-white hover:text-ink">Lihat yang baru</a>
                </div>
            </div>
        </section>
    @else
        <section class="bg-sand-100">
            <div class="shop-container grid items-center gap-8 py-10 sm:py-14 lg:grid-cols-12 lg:gap-12 lg:py-0">
                <div class="lg:col-span-6 lg:py-24 xl:col-span-5">
                    <p class="eyebrow text-stone-500">{{ config('shop.full_name') }}</p>
                    <h1 class="display mt-4 text-[13vw] leading-[0.88] sm:text-7xl lg:text-[5.5rem] xl:text-8xl">
                        Aksesoris original, <span class="text-brand-600">harga gudang.</span>
                    </h1>
                    <p class="mt-6 max-w-md text-base leading-relaxed text-stone-600 sm:text-lg">{{ config('shop.tagline') }}. Casing, charger, kabel, dan audio yang awet dipakai setiap hari.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('products.index') }}" class="btn btn-ink btn-pill">Belanja sekarang <x-icon name="arrow-right" class="size-4" /></a>
                        @guest
                            <a href="{{ route('register') }}" class="btn btn-outline btn-pill">Daftar gratis</a>
                        @else
                            <a href="{{ route('products.index', ['promo' => 1]) }}" class="btn btn-outline btn-pill">Lihat promo</a>
                        @endguest
                    </div>
                </div>

                @if ($heroProducts->isNotEmpty())
                    <div class="lg:col-span-6 xl:col-span-7">
                        <div class="grid grid-cols-5 gap-3 sm:gap-4 lg:py-10">
                            @foreach ($heroProducts->take(2) as $i => $product)
                                <a href="{{ route('products.show', $product) }}" @class(['group relative block', 'col-span-3' => $i === 0, 'col-span-2 self-end' => $i === 1])>
                                    <x-product-image :product="$product" :eager="true" bg="bg-white" :class="$i === 0 ? 'aspect-[4/5]' : 'aspect-[3/4]'" />
                                    <div class="absolute inset-x-3 bottom-3 flex items-end justify-between gap-2 bg-white/90 px-3 py-2 backdrop-blur sm:inset-x-4 sm:bottom-4">
                                        <p class="truncate text-xs font-semibold sm:text-sm">{{ $product->name }}</p>
                                        <p @class(['shrink-0 text-xs text-stone-600 sm:text-sm', 'max-sm:hidden' => $i === 1])>@rupiah($product->price)</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Keunggulan --}}
    <section class="border-b border-stone-200">
        <div class="shop-container no-scrollbar flex gap-8 overflow-x-auto py-5 sm:justify-between">
            @foreach ([['shield', 'Original & bergaransi'], ['truck', 'Kirim ke seluruh Indonesia'], ['credit-card', 'Transfer, e-wallet & QRIS'], ['chat', 'CS siap bantu tiap hari']] as [$icon, $label])
                <p class="flex shrink-0 items-center gap-2.5 text-[13px] font-medium text-stone-700">
                    <x-icon :name="$icon" class="size-5 text-ink" /> {{ $label }}
                </p>
            @endforeach
        </div>
    </section>

    {{-- Kategori --}}
    @if ($categories->isNotEmpty())
        <section class="shop-container mt-16 lg:mt-24">
            <div class="flex items-end justify-between gap-4">
                <h2 class="display text-3xl leading-none sm:text-5xl">Belanja per kategori</h2>
                <a href="{{ route('products.index') }}" class="link-underline hidden shrink-0 text-sm font-semibold sm:inline">Semua produk</a>
            </div>
            <div class="no-scrollbar -mx-4 mt-8 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-px-4 px-4 sm:mx-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:overflow-visible sm:px-0 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', ['kategori' => $category->slug]) }}"
                       class="group relative block w-[62%] shrink-0 snap-start overflow-hidden bg-sand-100 sm:w-auto">
                        <div class="aspect-[3/4]">
                            @if ($category->imageUrl())
                                <img src="{{ $category->imageUrl() }}" alt="" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-[1.04]">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-transparent"></div>
                            @else
                                <span class="absolute left-4 top-3 font-display text-6xl leading-none font-black tracking-[-0.05em] text-sand-300 sm:text-7xl">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            @endif
                        </div>
                        <div @class(['absolute inset-x-4 bottom-4', 'text-white' => $category->imageUrl(), 'text-ink' => ! $category->imageUrl()])>
                            <p class="font-display text-xl font-extrabold tracking-tight uppercase sm:text-2xl">{{ $category->name }}</p>
                            <p @class(['mt-1 flex items-center gap-1.5 text-xs font-semibold', 'text-white/80' => $category->imageUrl(), 'text-stone-500' => ! $category->imageUrl()])>
                                {{ $category->products_count }} produk
                                <x-icon name="arrow-right" class="size-3.5 transition group-hover:translate-x-1" />
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Unggulan / Baru masuk --}}
    @if ($featured->isNotEmpty() || $latest->isNotEmpty())
        <section class="shop-container mt-16 lg:mt-24" x-data="{ tab: '{{ $featured->isNotEmpty() ? 'featured' : 'latest' }}' }">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-6" role="tablist">
                    @foreach (array_filter(['featured' => $featured->isNotEmpty() ? 'Terlaris' : null, 'latest' => $latest->isNotEmpty() ? 'Baru masuk' : null]) as $key => $label)
                        <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'text-ink' : 'text-stone-300 hover:text-stone-500'"
                                class="display text-[26px] leading-none whitespace-nowrap transition sm:text-5xl">{{ $label }}</button>
                    @endforeach
                </div>
                <a href="{{ route('products.index') }}" class="link-underline shrink-0 text-sm font-semibold">Lihat semua</a>
            </div>

            @foreach (['featured' => $featured, 'latest' => $latest] as $key => $items)
                @if ($items->isNotEmpty())
                    <div x-show="tab === '{{ $key }}'" @if ($key !== 'featured' && $featured->isNotEmpty()) x-cloak @endif role="tabpanel"
                         class="no-scrollbar -mx-4 mt-8 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-px-4 px-4 sm:mx-0 sm:grid sm:grid-cols-3 sm:gap-x-5 sm:gap-y-10 sm:overflow-visible sm:px-0 lg:grid-cols-4">
                        @foreach ($items as $product)
                            <x-product-card :product="$product" class="w-[46%] shrink-0 snap-start sm:w-auto" />
                        @endforeach
                    </div>
                @endif
            @endforeach
        </section>
    @endif

    {{-- Banner editorial --}}
    <section class="shop-container mt-16 lg:mt-24">
        <div class="grid overflow-hidden bg-ink text-white lg:grid-cols-2">
            <div class="relative min-h-64 overflow-hidden bg-stone-800 lg:min-h-[520px]">
                @if ($image = $latest->first(fn ($p) => $p->imageUrl())?->imageUrl())
                    <img src="{{ $image }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover opacity-90">
                @else
                    <div class="absolute inset-0 grid place-items-center">
                        <span class="font-display text-[9rem] leading-none font-black tracking-[-0.06em] text-white/10 uppercase sm:text-[13rem]">{{ config('shop.name') }}</span>
                    </div>
                @endif
            </div>
            <div class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-16">
                <p class="eyebrow text-brand-400">Untuk reseller & toko</p>
                <h2 class="display mt-4 text-4xl leading-[0.92] sm:text-6xl">Belanja grosir, harga spesial.</h2>
                <p class="mt-5 max-w-md leading-relaxed text-white/70">Dapatkan harga khusus untuk pembelian dalam jumlah banyak. Stok gudang lengkap, siap kirim ke tokomu.</p>
                <div class="mt-8">
                    <a href="{{ config('shop.whatsapp') ? 'https://wa.me/'.config('shop.whatsapp') : route('products.index') }}" class="btn btn-light btn-pill" @if (config('shop.whatsapp')) target="_blank" rel="noopener" @endif>
                        Hubungi kami <x-icon name="arrow-right" class="size-4" />
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Promo --}}
    @if ($onSale->isNotEmpty())
        <section class="shop-container mt-16 lg:mt-24">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow text-brand-600">Hemat lebih banyak</p>
                    <h2 class="display mt-2 text-3xl leading-none sm:text-5xl">Lagi diskon</h2>
                </div>
                <a href="{{ route('products.index', ['promo' => 1]) }}" class="link-underline shrink-0 text-sm font-semibold">Semua promo</a>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 lg:grid-cols-4">
                @foreach ($onSale as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Janji layanan --}}
    <section class="shop-container mt-16 lg:mt-24">
        <div class="grid gap-px overflow-hidden border border-stone-200 bg-stone-200 sm:grid-cols-3">
            @foreach ([
                ['shield', 'Dijamin original', 'Semua produk berasal dari distributor resmi dan bergaransi toko.'],
                ['truck', 'Dikirim cepat', 'Pilih kurir favoritmu: JNE, SiCepat, J&T, POS, dan lainnya.'],
                ['refresh', 'Garansi tukar', 'Produk cacat pabrik? Kami bantu tukar tanpa ribet.'],
            ] as [$icon, $title, $desc])
                <div class="bg-white p-8 lg:p-10">
                    <x-icon :name="$icon" class="size-7" />
                    <h3 class="mt-5 font-display text-lg font-extrabold tracking-tight uppercase">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.shop>
