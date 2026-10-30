@php
    $heading = request('q') ? 'Hasil untuk "'.request('q').'"' : ($activeCategory?->name ?? (request()->boolean('promo') ? 'Promo' : (request()->boolean('grosir') ? 'Harga grosir' : 'Semua produk')));
    $baseQuery = request()->except('page');
    $pricePresets = [
        ['Di bawah 50rb', null, 50000],
        ['50rb – 100rb', 50000, 100000],
        ['100rb – 250rb', 100000, 250000],
        ['Di atas 250rb', 250000, null],
    ];

    // Filter aktif yang bisa dihapus satu per satu.
    $activeFilters = array_filter([
        'q' => request('q') ? 'Kata kunci: '.request('q') : null,
        'kategori' => $activeCategory ? 'Kategori: '.$activeCategory->name : null,
        'tersedia' => request()->boolean('tersedia') ? 'Tersedia' : null,
        'promo' => request()->boolean('promo') ? 'Sedang diskon' : null,
        'grosir' => request()->boolean('grosir') ? 'Bisa grosir' : null,
        'harga' => ($minPrice || $maxPrice)
            ? 'Harga: '.($minPrice ? \App\Support\Money::rupiah($minPrice) : 'Rp0').' – '.($maxPrice ? \App\Support\Money::rupiah($maxPrice) : '∞')
            : null,
    ]);
    $filterCount = count(array_diff_key($activeFilters, ['q' => true]));

    // Link kategori di panel filter: filter lain (kata kunci, harga, urutan, dll.) tetap dibawa.
    $categoryUrl = fn ($category = null) => route('products.index', ($category ? ['kategori' => $category->slug] : []) + \Illuminate\Support\Arr::except($baseQuery, 'kategori'));
    $openParentId = $activeCategory ? ($activeCategory->parent_id ?? $activeCategory->id) : null;
@endphp
@php
    $listingUrl = $activeCategory ? route('products.index', ['kategori' => $activeCategory->slug]) : route('products.index');
    $structuredData = \App\Support\StructuredData::script(
        \App\Support\StructuredData::collection($activeCategory?->seoTitle() ?? 'Semua Produk', $listingUrl, $products->getCollection()),
        \App\Support\StructuredData::breadcrumbs(array_values(array_filter([
            ['Beranda', route('home')],
            ['Produk', route('products.index')],
            $activeCategory?->parent ? [$activeCategory->parent->name, route('products.index', ['kategori' => $activeCategory->parent->slug])] : null,
            $activeCategory ? [$activeCategory->name, $listingUrl] : null,
        ]))),
    );
@endphp
<x-layouts.shop :title="$activeCategory?->seoTitle() ?? 'Semua Produk'" :description="$activeCategory?->metaDescription()">
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    <div x-data="{ filters: false }">
        {{-- Judul --}}
        <section class="border-b border-stone-200 bg-sand-50">
            <div class="shop-container pb-6 pt-8 sm:pt-12">
                <nav class="flex items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}" class="hover:text-ink">Beranda</a>
                    @if ($activeCategory?->parent)
                        <span>/</span>
                        <a href="{{ route('products.index', ['kategori' => $activeCategory->parent->slug]) }}" class="hover:text-ink">{{ $activeCategory->parent->name }}</a>
                    @endif
                    <span>/</span>
                    <span class="text-ink">{{ $activeCategory?->name ?? 'Produk' }}</span>
                </nav>
                <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                    <h1 class="display text-4xl leading-[0.92] break-words sm:text-6xl">{{ $heading }}</h1>
                    <p class="text-sm text-stone-500">{{ $products->total() }} produk</p>
                </div>
                @if ($activeCategory?->description && ! request('q'))
                    <p class="mt-4 max-w-3xl text-sm leading-relaxed whitespace-pre-line text-stone-600 sm:text-[15px]">{{ $activeCategory->description }}</p>
                @endif
            </div>
        </section>

        {{-- Toolbar --}}
        <div class="sticky top-16 z-30 border-b border-stone-200 bg-white/95 backdrop-blur lg:top-[72px]">
            <div class="shop-container flex h-14 items-center justify-between gap-3">
                <button type="button" @click="filters = true" class="-ml-2 flex min-h-11 items-center gap-2 px-2 text-sm font-semibold lg:hidden">
                    <x-icon name="filter" class="size-4" /> Filter
                    @if ($filterCount)
                        <span class="grid size-5 place-items-center rounded-full bg-ink text-[10px] text-white">{{ $filterCount }}</span>
                    @endif
                </button>
                <p class="hidden text-sm text-stone-500 lg:block">{{ number_format($products->total(), 0, ',', '.') }} produk</p>
                <form method="GET" x-data @change="$el.submit()" class="flex items-center gap-2">
                    @foreach (\Illuminate\Support\Arr::except($baseQuery, 'urut') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label for="sort" class="hidden text-sm text-stone-500 sm:block">Urutkan</label>
                    <select id="sort" name="urut" class="rounded-full border-stone-300 py-2 pl-4 pr-9 text-base font-semibold lg:text-sm focus:border-ink focus:ring-ink/10">
                        @foreach ($sorts as $key => $label)
                            <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="shop-container mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[230px_1fr] xl:gap-14">
            {{-- Filter: sidebar di desktop, drawer di mobile --}}
            <div x-show="filters" x-cloak x-transition.opacity @click="filters = false" class="fixed inset-0 z-50 bg-ink/40 lg:hidden"></div>
            <aside :class="{ 'max-lg:-translate-x-full': ! filters }"
                   class="fixed inset-y-0 left-0 z-50 flex w-[86%] max-w-sm flex-col bg-white transition-transform duration-300 max-lg:-translate-x-full lg:sticky lg:top-[152px] lg:z-auto lg:max-h-[calc(100dvh-168px)] lg:overflow-y-auto lg:-mx-1 lg:pl-1 lg:pr-3 thin-scrollbar lg:w-auto lg:max-w-none lg:self-start lg:bg-transparent lg:transition-none"
                   x-fit-viewport aria-label="Filter produk">
                <div class="flex h-16 items-center justify-between border-b border-stone-200 px-5 lg:hidden">
                    <p class="display text-xl">Filter</p>
                    <button type="button" @click="filters = false" class="-mr-2 grid size-10 place-items-center rounded-full hover:bg-sand-100" aria-label="Tutup filter"><x-icon name="x" /></button>
                </div>

                <form method="GET" action="{{ route('products.index') }}" class="flex flex-1 flex-col overflow-y-auto lg:overflow-visible"
                      x-data="{ min: @js(\App\Support\FormField::thousands($minPrice)), max: @js(\App\Support\FormField::thousands($maxPrice)) }">
                    @foreach (\Illuminate\Support\Arr::only($baseQuery, ['q', 'kategori', 'urut']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <div class="flex-1 divide-y divide-stone-200 px-5 lg:px-0">
                        @if ($categories->isNotEmpty())
                            <div class="py-5 lg:pt-0" role="group" aria-labelledby="f-category">
                                <h3 id="f-category" class="eyebrow text-stone-500">Kategori</h3>
                                <ul class="mt-2 lg:mt-3">
                                    <li>
                                        <a href="{{ $categoryUrl() }}" @if (! $activeCategory) aria-current="page" @endif
                                           @class(['filter-cat', 'filter-cat-active' => ! $activeCategory])>
                                            <span class="min-w-0 flex-1 break-words leading-snug">Semua kategori</span>
                                        </a>
                                    </li>
                                    @foreach ($categories as $category)
                                        @php($isOpen = $openParentId === $category->id)
                                        <li x-data="{ open: @js($isOpen) }">
                                            <div class="flex items-center">
                                                <a href="{{ $categoryUrl($category) }}" @if ($activeCategory?->is($category)) aria-current="page" @endif
                                                   @class(['filter-cat min-w-0 flex-1', 'filter-cat-active' => $activeCategory?->is($category), 'font-semibold text-ink' => $isOpen && ! $activeCategory?->is($category)])>
                                                    <span class="min-w-0 flex-1 break-words leading-snug">{{ $category->name }}</span>
                                                    <span class="shrink-0 text-xs tabular-nums text-stone-400">{{ $category->product_count }}</span>
                                                </a>
                                                @if ($category->children->isNotEmpty())
                                                    <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="f-cat-{{ $category->id }}"
                                                            class="-mr-2 grid size-11 shrink-0 place-items-center text-stone-400 hover:text-ink lg:size-8 lg:-mr-1">
                                                        <x-icon name="chevron-down" class="size-4 transition" x-bind:class="open && 'rotate-180'" />
                                                        <span class="sr-only">Subkategori {{ $category->name }}</span>
                                                    </button>
                                                @endif
                                            </div>
                                            @if ($category->children->isNotEmpty())
                                                <ul id="f-cat-{{ $category->id }}" x-show="open" @if (! $isOpen) x-cloak @endif class="mb-1 ml-3 border-l border-stone-200 pl-3">
                                                    @foreach ($category->children as $child)
                                                        <li>
                                                            <a href="{{ $categoryUrl($child) }}" @if ($activeCategory?->is($child)) aria-current="page" @endif
                                                               @class(['filter-cat text-[13px]', 'filter-cat-active' => $activeCategory?->is($child)])>
                                                                <span class="min-w-0 flex-1 break-words leading-snug">{{ $child->name }}</span>
                                                                <span class="shrink-0 text-xs tabular-nums text-stone-400">{{ $child->product_count }}</span>
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div @class(['py-5', 'lg:pt-0' => $categories->isEmpty()]) role="group" aria-labelledby="f-stock">
                            <h3 id="f-stock" class="eyebrow text-stone-500">Ketersediaan</h3>
                            <div class="mt-2 lg:mt-4 lg:space-y-3">
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm lg:min-h-0">
                                    <input type="checkbox" name="tersedia" value="1" class="size-4 rounded-none border-stone-400 text-ink focus:ring-ink/20" @checked(request()->boolean('tersedia'))>
                                    Hanya yang tersedia
                                </label>
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm lg:min-h-0">
                                    <input type="checkbox" name="promo" value="1" class="size-4 rounded-none border-stone-400 text-ink focus:ring-ink/20" @checked(request()->boolean('promo'))>
                                    Sedang diskon
                                </label>
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm lg:min-h-0">
                                    <input type="checkbox" name="grosir" value="1" class="size-4 rounded-none border-stone-400 text-ink focus:ring-ink/20" @checked(request()->boolean('grosir'))>
                                    Bisa grosir
                                </label>
                            </div>
                        </div>

                        <div class="py-5" role="group" aria-labelledby="f-price">
                            <h3 id="f-price" class="eyebrow text-stone-500">Harga</h3>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($pricePresets as [$label, $from, $to])
                                    <button type="button" @click="min = @js(\App\Support\FormField::thousands($from)); max = @js(\App\Support\FormField::thousands($to))"
                                            :class="min == @js(\App\Support\FormField::thousands($from)) && max == @js(\App\Support\FormField::thousands($to)) ? 'chip-active' : ''"
                                            class="chip px-3 py-1.5 text-xs">{{ $label }}</button>
                                @endforeach
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <label class="sr-only" for="harga_min">Harga minimum</label>
                                <input id="harga_min" type="text" name="harga_min" x-model="min" x-money placeholder="Min Rp"
                                       class="w-full border-stone-300 px-3 py-2.5 text-base lg:py-2 lg:text-sm focus:border-ink focus:ring-ink/10">
                                <label class="sr-only" for="harga_max">Harga maksimum</label>
                                <input id="harga_max" type="text" name="harga_max" x-model="max" x-money placeholder="Maks Rp"
                                       class="w-full border-stone-300 px-3 py-2.5 text-base lg:py-2 lg:text-sm focus:border-ink focus:ring-ink/10">
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 grid grid-cols-2 gap-2 border-t border-stone-200 bg-white p-5 lg:px-0 lg:pt-3 lg:pb-1">
                        <a href="{{ route('products.index', \Illuminate\Support\Arr::only($baseQuery, ['q', 'urut'])) }}" class="btn btn-outline rounded-full py-2.5">Reset</a>
                        <button class="btn btn-ink rounded-full py-2.5">Terapkan</button>
                    </div>
                </form>
            </aside>

            {{-- Grid produk --}}
            <div class="min-w-0">
                @if ($activeFilters)
                    <div class="mb-6 flex flex-wrap items-center gap-2">
                        @foreach ($activeFilters as $key => $label)
                            @php($without = $key === 'harga' ? \Illuminate\Support\Arr::except($baseQuery, ['harga_min', 'harga_max']) : \Illuminate\Support\Arr::except($baseQuery, $key))
                            <a href="{{ route('products.index', $without) }}" class="inline-flex items-center gap-1.5 rounded-full bg-sand-100 px-3 py-1.5 text-xs font-medium hover:bg-sand-200">
                                {{ $label }} <x-icon name="x" class="size-3.5" /><span class="sr-only">Hapus filter</span>
                            </a>
                        @endforeach
                        @if (count($activeFilters) > 1)
                            <a href="{{ route('products.index', \Illuminate\Support\Arr::only($baseQuery, ['urut'])) }}" class="link-underline px-1 text-xs font-semibold">Hapus semua</a>
                        @endif
                    </div>
                @endif

                @if ($products->isEmpty())
                    <div class="border border-dashed border-stone-300">
                        <x-empty-state icon="search" title="Produk tidak ditemukan" description="Coba kata kunci lain atau kurangi filter yang dipakai.">
                            <a href="{{ route('products.index') }}" class="btn btn-ink btn-pill">Lihat semua produk</a>
                        </x-empty-state>
                    </div>
                @else
                    @fragment('infinite-list')
                        <x-infinite-list :paginator="$products" label="produk" skeleton="product" :skeleton-count="6"
                                         items-class="grid grid-cols-2 gap-x-3 gap-y-10 sm:gap-x-5 md:grid-cols-3 2xl:grid-cols-4">
                            @foreach ($products as $product)
                                <x-product-card :product="$product" />
                            @endforeach
                        </x-infinite-list>
                    @endfragment
                @endif
            </div>
        </div>
    </div>
</x-layouts.shop>
