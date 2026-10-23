@php
    $hero = \App\Support\Storefront::hero();
    $sorts = ['terlaris' => 'Terlaris', 'terbaru' => 'Terbaru', 'termurah' => 'Harga terendah', 'termahal' => 'Harga tertinggi'];
    // Peringkat tiap produk untuk setiap urutan (diurutkan di browser tanpa muat ulang).
    $byPrice = $bestSellers->sortBy('price')->pluck('id')->flip();
    $last = $bestSellers->count() - 1;
@endphp
<x-layouts.shop>
    <x-slot:head>
        {!! \App\Support\StructuredData::script(\App\Support\StructuredData::website()) !!}
        <link rel="preload" as="image" href="{{ $hero['image'] }}" @if ($hero['mobile_image']) media="(min-width: 768px)" @endif>
        @if ($hero['mobile_image'])
            <link rel="preload" as="image" href="{{ $hero['mobile_image'] }}" media="(max-width: 767px)">
        @endif
    </x-slot:head>

    {{-- Hero: foto besar selebar layar dengan judul & tombol (diatur di Admin › Tampilan Beranda) --}}
    <section @class(['relative isolate flex overflow-hidden bg-brand-950', 'text-white' => $hero['light'], 'text-ink' => ! $hero['light']])>
        <picture class="absolute inset-0 -z-20">
            @if ($hero['mobile_image'])
                <source media="(max-width: 767px)" srcset="{{ $hero['mobile_image'] }}">
            @endif
            <img src="{{ $hero['image'] }}" alt="" fetchpriority="high" decoding="async" class="size-full object-cover">
        </picture>
        @if ($hero['light'])
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/70 via-black/25 to-transparent md:bg-gradient-to-r md:from-black/55 md:via-black/15"></div>
        @else
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-white/80 via-white/30 to-transparent md:bg-gradient-to-r md:from-white/70 md:via-white/20"></div>
        @endif

        {{-- Hampir setinggi layar, disisakan ±80px supaya baris tab Best Seller/Kategori langsung terlihat. --}}
        <div class="shop-container flex min-h-[calc(100svh-180px)] max-h-[780px] items-end pt-24 pb-10 sm:pb-14 md:min-h-[min(calc(100vh-196px),760px)] md:items-center md:py-20 md:[@media(max-height:760px)]:py-8">
            <div class="w-full max-w-xl">
                @if ($hero['eyebrow'])
                    <p @class(['text-xs font-semibold tracking-[0.2em] uppercase sm:text-[13px]', 'text-white/85' => $hero['light'], 'text-brand-700' => ! $hero['light']])>{{ $hero['eyebrow'] }}</p>
                @endif
                <h1 class="mt-3 text-[2.4rem] leading-[1.02] font-bold tracking-tight text-balance sm:text-6xl lg:text-7xl md:[@media(max-height:760px)]:text-5xl">{{ $hero['title'] }}</h1>
                @if ($hero['subtitle'])
                    <p @class(['mt-4 max-w-md text-[15px] leading-relaxed sm:text-lg', 'text-white/85' => $hero['light'], 'text-stone-700' => ! $hero['light']])>{{ $hero['subtitle'] }}</p>
                @endif
                @if ($hero['primary'] || $hero['secondary'])
                    <div class="mt-7 grid grid-cols-2 gap-3 sm:mt-9 sm:flex sm:flex-wrap">
                        @foreach (array_filter([$hero['primary'], $hero['secondary']]) as $cta)
                            <a href="{{ $cta['url'] }}" @class([
                                'inline-flex min-h-12 items-center justify-center rounded-md px-3 text-center text-[11px] font-bold tracking-[0.1em] whitespace-nowrap uppercase transition sm:min-w-48 sm:px-8 sm:text-[13px] sm:tracking-[0.14em]',
                                'bg-white text-ink hover:bg-brand-50' => $hero['light'],
                                'bg-ink text-white hover:bg-brand-900' => ! $hero['light'],
                                'col-span-2' => ! ($hero['primary'] && $hero['secondary']),
                            ])>{{ $cta['label'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Best Seller / Kategori --}}
    @if ($bestSellers->isNotEmpty() || $categories->isNotEmpty())
        <section class="shop-container mt-4 sm:mt-6"
                 x-data="{ tab: '{{ $bestSellers->isNotEmpty() ? 'best' : 'categories' }}', sort: 'terlaris' }">
            <div class="grid grid-cols-2 border-b-2 border-stone-200" role="tablist" aria-label="Produk pilihan">
                @foreach (['best' => 'Best Seller', 'categories' => 'Kategori'] as $key => $label)
                    <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                            @click="tab = '{{ $key }}'" :aria-selected="(tab === '{{ $key }}').toString()"
                            :class="tab === '{{ $key }}' ? 'border-ink text-ink' : 'border-transparent text-stone-500 hover:text-ink'"
                            class="-mb-[2px] border-b-2 py-3 text-sm font-medium transition sm:text-base">{{ $label }}</button>
                @endforeach
            </div>

            <div class="mt-4 flex min-h-10 items-center justify-between gap-3">
                <p class="text-xs text-stone-500 sm:text-sm">
                    <span x-show="tab === 'best'">{{ $bestSellers->count() }} produk<span class="max-sm:hidden"> paling diminati</span></span>
                    <span x-show="tab === 'categories'" x-cloak>{{ $categories->count() }} kategori</span>
                </p>
                <label x-show="tab === 'best'" class="flex h-10 items-center gap-1 rounded-md border border-stone-200 bg-white pl-3 text-xs sm:text-sm">
                    <span class="font-semibold">Urutkan:</span>
                    <select x-model="sort" class="h-full border-0 bg-transparent py-0 pr-8 pl-1 text-xs focus:ring-0 sm:text-sm">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div id="panel-best" role="tabpanel" aria-labelledby="tab-best" x-show="tab === 'best'" @if ($bestSellers->isEmpty()) x-cloak @endif>
                @if ($bestSellers->isNotEmpty())
                    <div class="mt-4 grid grid-cols-2 gap-x-2 gap-y-3 sm:gap-x-4 sm:gap-y-5 md:grid-cols-3 xl:grid-cols-4">
                        @foreach ($bestSellers as $product)
                            @php($ranks = ['terlaris' => $product->rank_best, 'terbaru' => $product->rank_new, 'termurah' => $byPrice[$product->id], 'termahal' => $last - $byPrice[$product->id]])
                            <x-product-tile :product="$product" :eager="$loop->index < 4" x-bind:style="'order: ' + {{ json_encode($ranks) }}[sort]" />
                        @endforeach
                    </div>
                    <div class="mt-8 text-center">
                        <a href="{{ route('products.index') }}" x-bind:href="@js(route('products.index')) + (sort === 'terlaris' ? '' : '?urut=' + sort)"
                           class="btn btn-outline btn-pill">Lihat semua produk <x-icon name="arrow-right" class="size-4" /></a>
                    </div>
                @else
                    <x-empty-state title="Belum ada produk" />
                @endif
            </div>

            <div id="panel-categories" role="tabpanel" aria-labelledby="tab-categories" x-show="tab === 'categories'" @if ($bestSellers->isNotEmpty()) x-cloak @endif>
                <div class="mt-4 grid grid-cols-2 gap-x-2 gap-y-3 sm:gap-x-4 sm:gap-y-5 md:grid-cols-3 xl:grid-cols-4">
                    @foreach ($categories as $category)
                        <a href="{{ route('products.index', ['kategori' => $category->slug]) }}" class="group flex flex-col rounded-lg transition-colors hover:bg-stone-100">
                            <div class="relative aspect-square overflow-hidden rounded-lg bg-stone-200/70">
                                @if ($category->imageUrl())
                                    <img src="{{ $category->imageUrl() }}" alt="" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-[1.04]">
                                @else
                                    <div class="grid size-full place-items-center">
                                        <div class="flex flex-col items-center gap-3 text-center">
                                            <x-logo variant="mark" class="h-9 opacity-25 grayscale" aria-hidden="true" />
                                            <span class="px-4 text-lg font-bold tracking-tight text-stone-400/80 sm:text-2xl lg:text-3xl">{{ $category->name }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center justify-between gap-2 px-2 pt-2.5 pb-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-[13px] font-medium text-ink sm:text-sm">{{ $category->name }}</h3>
                                    <p class="mt-0.5 text-xs text-brand-700 sm:text-[13px]">{{ $category->products_count }} produk</p>
                                </div>
                                <x-icon name="arrow-right" class="size-4 shrink-0 text-stone-400 transition group-hover:translate-x-1 group-hover:text-ink" />
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($banners->isNotEmpty())
        @include('shop.partials.banners')
    @endif

    {{-- Banner editorial --}}
    <section class="shop-container mt-16 lg:mt-24">
        <div class="grid overflow-hidden bg-ink text-white lg:grid-cols-2">
            <div class="relative min-h-64 overflow-hidden bg-stone-800 lg:min-h-[520px]">
                @if ($image = $bestSellers->first(fn ($p) => $p->imageUrl())?->imageUrl())
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
