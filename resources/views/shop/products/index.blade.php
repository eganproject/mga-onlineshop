@php
    $heading = request('q') ? 'Hasil untuk "'.request('q').'"' : ($activeCategory?->name ?? (request()->boolean('promo') ? 'Promo' : 'Semua produk'));
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
        'tersedia' => request()->boolean('tersedia') ? 'Tersedia' : null,
        'promo' => request()->boolean('promo') ? 'Sedang diskon' : null,
        'harga' => ($minPrice || $maxPrice)
            ? 'Harga: '.($minPrice ? \App\Support\Money::rupiah($minPrice) : 'Rp0').' – '.($maxPrice ? \App\Support\Money::rupiah($maxPrice) : '∞')
            : null,
    ]);
    $filterCount = count(array_diff_key($activeFilters, ['q' => true]));
@endphp
@php
    $metaDescription = $activeCategory
        ? ($activeCategory->description ?: 'Belanja '.$activeCategory->name.' original di '.config('shop.full_name').'. Harga terjangkau, kirim ke seluruh Indonesia.')
        : null;
@endphp
<x-layouts.shop :title="$activeCategory?->name ?? 'Semua Produk'" :description="$metaDescription">
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

                {{-- Kategori --}}
                @if ($categories->isNotEmpty())
                    <div class="no-scrollbar -mx-4 mt-6 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
                        <a href="{{ route('products.index', \Illuminate\Support\Arr::except($baseQuery, 'kategori')) }}" @class(['chip', 'chip-active' => ! $activeCategory])>Semua</a>
                        @foreach ($categories as $category)
                            <a href="{{ route('products.index', ['kategori' => $category->slug] + \Illuminate\Support\Arr::except($baseQuery, 'kategori')) }}"
                               @class(['chip', 'chip-active' => $activeCategory?->is($category) || ($activeCategory && (int) $activeCategory->parent_id === $category->id)])>{{ $category->name }}</a>
                        @endforeach
                    </div>
                    {{-- Subkategori dari kategori induk yang sedang dibuka --}}
                    @php($openParent = $activeCategory ? $categories->first(fn ($c) => $activeCategory->is($c) || (int) $activeCategory->parent_id === $c->id) : null)
                    @if ($openParent?->children->isNotEmpty())
                        <div class="no-scrollbar -mx-4 mt-3 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
                            <a href="{{ route('products.index', ['kategori' => $openParent->slug] + \Illuminate\Support\Arr::except($baseQuery, 'kategori')) }}"
                               @class(['chip px-3 py-1.5 text-xs', 'chip-active' => $activeCategory->is($openParent)])>Semua {{ $openParent->name }}</a>
                            @foreach ($openParent->children as $child)
                                <a href="{{ route('products.index', ['kategori' => $child->slug] + \Illuminate\Support\Arr::except($baseQuery, 'kategori')) }}"
                                   @class(['chip px-3 py-1.5 text-xs', 'chip-active' => $activeCategory->is($child)])>{{ $child->name }}</a>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </section>

        {{-- Toolbar --}}
        <div class="sticky top-16 z-30 border-b border-stone-200 bg-white/95 backdrop-blur lg:top-[72px]">
            <div class="shop-container flex h-14 items-center justify-between gap-3">
                <button type="button" @click="filters = true" class="flex items-center gap-2 text-sm font-semibold lg:hidden">
                    <x-icon name="filter" class="size-4" /> Filter
                    @if ($filterCount)
                        <span class="grid size-5 place-items-center rounded-full bg-ink text-[10px] text-white">{{ $filterCount }}</span>
                    @endif
                </button>
                <p class="hidden text-sm text-stone-500 lg:block">Menampilkan {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} dari {{ $products->total() }}</p>
                <form method="GET" x-data @change="$el.submit()" class="flex items-center gap-2">
                    @foreach (\Illuminate\Support\Arr::except($baseQuery, 'urut') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label for="sort" class="hidden text-sm text-stone-500 sm:block">Urutkan</label>
                    <select id="sort" name="urut" class="rounded-full border-stone-300 py-2 pl-4 pr-9 text-sm font-semibold focus:border-ink focus:ring-ink/10">
                        @foreach ($sorts as $key => $label)
                            <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="shop-container mt-8 grid gap-10 lg:grid-cols-[230px_1fr] xl:gap-14">
            {{-- Filter: sidebar di desktop, drawer di mobile --}}
            <div x-show="filters" x-cloak x-transition.opacity @click="filters = false" class="fixed inset-0 z-50 bg-ink/40 lg:hidden"></div>
            <aside :class="{ 'max-lg:-translate-x-full': ! filters }"
                   class="fixed inset-y-0 left-0 z-50 flex w-[86%] max-w-sm flex-col bg-white transition-transform duration-300 max-lg:-translate-x-full lg:sticky lg:top-[152px] lg:z-auto lg:w-auto lg:max-w-none lg:self-start lg:bg-transparent lg:transition-none"
                   aria-label="Filter produk">
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
                        <div class="py-5 lg:pt-0" role="group" aria-labelledby="f-stock">
                            <h3 id="f-stock" class="eyebrow text-stone-500">Ketersediaan</h3>
                            <div class="mt-4 space-y-3">
                                <label class="flex cursor-pointer items-center gap-3 text-sm">
                                    <input type="checkbox" name="tersedia" value="1" class="size-4 rounded-none border-stone-400 text-ink focus:ring-ink/20" @checked(request()->boolean('tersedia'))>
                                    Hanya yang tersedia
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 text-sm">
                                    <input type="checkbox" name="promo" value="1" class="size-4 rounded-none border-stone-400 text-ink focus:ring-ink/20" @checked(request()->boolean('promo'))>
                                    Sedang diskon
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
                                       class="w-full border-stone-300 px-3 py-2 text-sm focus:border-ink focus:ring-ink/10">
                                <label class="sr-only" for="harga_max">Harga maksimum</label>
                                <input id="harga_max" type="text" name="harga_max" x-model="max" x-money placeholder="Maks Rp"
                                       class="w-full border-stone-300 px-3 py-2 text-sm focus:border-ink focus:ring-ink/10">
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 grid grid-cols-2 gap-2 border-t border-stone-200 bg-white p-5 lg:static lg:mt-2 lg:border-0 lg:bg-transparent lg:p-0">
                        <a href="{{ route('products.index', \Illuminate\Support\Arr::only($baseQuery, ['q', 'kategori', 'urut'])) }}" class="btn btn-outline rounded-full py-2.5">Reset</a>
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
                            <a href="{{ route('products.index', \Illuminate\Support\Arr::only($baseQuery, ['kategori'])) }}" class="link-underline px-1 text-xs font-semibold">Hapus semua</a>
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
                    <div class="grid grid-cols-2 gap-x-3 gap-y-10 sm:gap-x-5 md:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($products as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>
                    <div class="mt-14">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.shop>
