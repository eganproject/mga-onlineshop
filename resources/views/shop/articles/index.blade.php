@php
    $filtered = $activeCategory || $tag || $search;
    $featured = ! $filtered && $articles->onFirstPage() ? $articles->first() : null;
    $list = $featured ? $articles->getCollection()->slice(1) : $articles->getCollection();
    $heading = $activeCategory?->name ?? ($tag ? '#'.$tag : 'Artikel');
    $description = $activeCategory?->description ?: 'Tips, panduan memilih, dan kabar terbaru seputar media promosi & display akrilik dari '.config('shop.full_name').'.';
@endphp
<x-layouts.shop :title="$activeCategory ? $activeCategory->name.' · Artikel' : ($tag ? 'Artikel tentang '.$tag : 'Artikel & Tips')" :description="$description">
    <x-slot:head>
        {!! \App\Support\StructuredData::script(\App\Support\StructuredData::breadcrumbs(array_values(array_filter([
            ['Beranda', route('home')],
            ['Artikel', route('articles.index')],
            $activeCategory ? [$activeCategory->name, route('articles.index', ['kategori' => $activeCategory->slug])] : null,
        ])))) !!}
    </x-slot:head>

    <div class="shop-container pt-8 lg:pt-12">
        <div class="flex flex-col gap-6 border-b border-stone-200 pb-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="eyebrow text-brand-600">{{ $filtered ? 'Artikel' : 'Jurnal '.config('shop.name') }}</p>
                <h1 class="mt-2 text-4xl leading-none font-bold tracking-tight text-ink sm:text-5xl">{{ $heading }}</h1>
                <p class="mt-3 text-stone-600">{{ $description }}</p>
            </div>
            <form action="{{ route('articles.index') }}" method="GET" role="search" class="w-full lg:w-72">
                @if ($activeCategory) <input type="hidden" name="kategori" value="{{ $activeCategory->slug }}"> @endif
                <label class="relative block">
                    <span class="sr-only">Cari artikel</span>
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" name="q" value="{{ $search }}" placeholder="Cari artikel..."
                           class="h-11 w-full rounded-full border-0 bg-stone-100 pl-10 pr-4 text-base placeholder:text-stone-400 lg:text-sm focus:bg-white focus:ring-2 focus:ring-brand-500/40">
                </label>
            </form>
        </div>

        @if ($categories->isNotEmpty())
            <nav class="no-scrollbar -mx-4 mt-5 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0" aria-label="Kategori artikel">
                <a href="{{ route('articles.index') }}" @class(['chip', 'chip-active' => ! $activeCategory && ! $tag])>Semua</a>
                @foreach ($categories as $category)
                    <a href="{{ route('articles.index', ['kategori' => $category->slug]) }}" @class(['chip', 'chip-active' => $activeCategory?->is($category)])>{{ $category->name }}</a>
                @endforeach
            </nav>
        @endif

        @if ($tag || $search)
            <p class="mt-5 flex flex-wrap items-center gap-2 text-sm text-stone-600">
                {{ $articles->total() }} artikel
                @if ($search) untuk “<span class="font-semibold text-ink">{{ $search }}</span>” @endif
                @if ($tag) dengan tag <span class="font-semibold text-ink">#{{ $tag }}</span> @endif
                <a href="{{ route('articles.index', array_filter(['kategori' => $activeCategory?->slug])) }}" class="link-underline font-semibold text-ink">Hapus filter</a>
            </p>
        @endif

        @if ($articles->isEmpty())
            <x-empty-state icon="newspaper" class="mt-10" :title="$filtered ? 'Artikel tidak ditemukan' : 'Belum ada artikel'"
                :description="$filtered ? 'Coba kata kunci atau kategori lain.' : 'Nantikan tips dan panduan dari tim kami.'">
                <a href="{{ $filtered ? route('articles.index') : route('products.index') }}" class="btn btn-ink btn-pill">{{ $filtered ? 'Lihat semua artikel' : 'Mulai belanja' }}</a>
            </x-empty-state>
        @else
            @if ($featured)
                <x-article-card :article="$featured" large eager class="mt-8 lg:mt-10" />
            @endif
            @if ($list->isNotEmpty())
                @fragment('infinite-list')
                    <x-infinite-list :paginator="$articles" label="artikel" skeleton="article" :skeleton-count="3"
                                     class="{{ $featured ? 'mt-14 border-t border-stone-200 pt-12' : 'mt-8' }}"
                                     items-class="grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($list as $article)
                            <x-article-card :article="$article" :eager="! $featured && $loop->index < 3" />
                        @endforeach
                    </x-infinite-list>
                @endfragment
            @endif
        @endif
    </div>
</x-layouts.shop>
