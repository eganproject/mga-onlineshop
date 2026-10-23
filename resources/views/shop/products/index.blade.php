<x-layouts.shop :title="$activeCategory?->name ?? 'Semua Produk'">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" x-data="{ filters: false }">
        <nav class="mb-4 flex items-center gap-1.5 text-xs text-stone-500">
            <a href="{{ route('home') }}" class="hover:text-stone-800">Beranda</a>
            <x-icon name="chevron-right" class="size-3" />
            <span class="text-stone-800">{{ $activeCategory?->name ?? 'Semua produk' }}</span>
        </nav>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">
                    @if (request('q')) Hasil untuk "{{ request('q') }}" @else {{ $activeCategory?->name ?? 'Semua produk' }} @endif
                </h1>
                <p class="mt-1 text-sm text-stone-500">{{ $products->total() }} produk ditemukan</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="filters = true" class="btn btn-secondary lg:hidden"><x-icon name="filter" class="size-4" /> Filter</button>
                <form method="GET" x-data @change="$el.submit()">
                    @foreach (request()->except('urut', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select name="urut" class="input w-auto pr-9" aria-label="Urutkan">
                        @foreach ($sorts as $key => $label)
                            <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="mt-6 grid gap-8 lg:grid-cols-[240px_1fr]">
            {{-- Filter --}}
            <div x-show="filters" x-cloak @click="filters = false" class="fixed inset-0 z-40 bg-stone-900/40 lg:hidden"></div>
            <aside :class="filters ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'"
                   class="fixed inset-x-0 bottom-0 z-50 max-h-[80vh] translate-y-full overflow-y-auto rounded-t-3xl bg-white p-6 transition-transform lg:static lg:z-auto lg:max-h-none lg:translate-y-0 lg:rounded-none lg:bg-transparent lg:p-0">
                <div class="mb-4 flex items-center justify-between lg:hidden">
                    <h2 class="text-lg font-bold">Filter</h2>
                    <button @click="filters = false" class="btn btn-ghost p-2"><x-icon name="x" /></button>
                </div>
                <h3 class="text-xs font-bold tracking-wider text-stone-400 uppercase">Kategori</h3>
                <ul class="mt-3 space-y-1">
                    <li>
                        <a href="{{ route('products.index', request()->except('kategori', 'page')) }}"
                           @class(['flex items-center justify-between rounded-xl px-3 py-2 text-sm font-medium transition', 'bg-brand-50 text-brand-700' => ! $activeCategory, 'text-stone-600 hover:bg-white' => $activeCategory])>
                            Semua kategori
                        </a>
                    </li>
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('products.index', ['kategori' => $category->slug] + request()->except('kategori', 'page')) }}"
                               @class(['flex items-center justify-between rounded-xl px-3 py-2 text-sm font-medium transition', 'bg-brand-50 text-brand-700' => $activeCategory?->is($category), 'text-stone-600 hover:bg-white' => ! $activeCategory?->is($category)])>
                                {{ $category->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                <h3 class="mt-6 text-xs font-bold tracking-wider text-stone-400 uppercase">Ketersediaan</h3>
                <form method="GET" class="mt-3" x-data @change="$el.submit()">
                    @foreach (request()->except('tersedia', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label class="flex items-center gap-2 px-3 text-sm text-stone-600">
                        <input type="checkbox" name="tersedia" value="1" class="checkbox" @checked(request()->boolean('tersedia'))> Hanya yang tersedia
                    </label>
                </form>
            </aside>

            {{-- Grid produk --}}
            <div>
                @if ($products->isEmpty())
                    <div class="card">
                        <x-empty-state icon="search" title="Produk tidak ditemukan" description="Coba kata kunci lain atau hapus filter.">
                            <a href="{{ route('products.index') }}" class="btn btn-secondary">Lihat semua produk</a>
                        </x-empty-state>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3">
                        @foreach ($products as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.shop>
