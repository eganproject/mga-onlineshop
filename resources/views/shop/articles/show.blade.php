<x-layouts.shop :title="$article->seoTitle()" :description="$article->metaDescription()" :image="$article->coverUrl()" type="article" :noindex="$preview">
    <x-slot:head>
        @if ($article->published_at)
            <meta property="article:published_time" content="{{ $article->published_at->toAtomString() }}">
        @endif
        <meta property="article:modified_time" content="{{ $article->updated_at->toAtomString() }}">
        @if ($article->category)
            <meta property="article:section" content="{{ $article->category->name }}">
        @endif
        @foreach ($article->tagList() as $tag)
            <meta property="article:tag" content="{{ $tag }}">
        @endforeach
        @unless ($preview)
            {!! \App\Support\StructuredData::script(
                \App\Support\StructuredData::article($article),
                \App\Support\StructuredData::breadcrumbs(array_values(array_filter([
                    ['Beranda', route('home')],
                    ['Artikel', route('articles.index')],
                    $article->category ? [$article->category->name, route('articles.index', ['kategori' => $article->category->slug])] : null,
                    [$article->title, route('articles.show', $article)],
                ]))),
            ) !!}
        @endunless
    </x-slot:head>

    @if ($preview)
        <div class="bg-sky-50 text-sky-900">
            <div class="shop-container flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                <p class="flex items-center gap-2"><x-icon name="eye" class="size-4" />
                    <span><strong>Pratinjau.</strong> {{ $article->state() === 'scheduled' ? 'Artikel ini dijadwalkan terbit '.$article->published_at->translatedFormat('j M Y, H:i').'.' : 'Artikel ini masih draf dan belum terlihat pelanggan.' }}</span>
                </p>
                <a href="{{ route('admin.articles.edit', $article) }}" class="font-semibold underline underline-offset-4">Kembali ke editor</a>
            </div>
        </div>
    @endif

    <article>
        <header class="shop-container pt-6 lg:pt-10">
            <nav class="mx-auto flex max-w-3xl flex-wrap items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-ink">Beranda</a>
                <span>/</span>
                <a href="{{ route('articles.index') }}" class="hover:text-ink">Artikel</a>
                @if ($article->category)
                    <span>/</span>
                    <a href="{{ route('articles.index', ['kategori' => $article->category->slug]) }}" class="hover:text-ink">{{ $article->category->name }}</a>
                @endif
            </nav>

            <div class="mx-auto mt-6 max-w-3xl text-center sm:mt-10">
                @if ($article->category)
                    <a href="{{ route('articles.index', ['kategori' => $article->category->slug]) }}" class="eyebrow text-brand-600 hover:text-brand-800">{{ $article->category->name }}</a>
                @endif
                <h1 class="mt-3 text-3xl leading-[1.1] font-bold tracking-tight text-ink text-balance sm:text-5xl">{{ $article->title }}</h1>
                @if ($article->excerpt)
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-stone-600 sm:text-lg">{{ $article->excerpt }}</p>
                @endif
                <p class="mt-5 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-sm text-stone-500">
                    <span class="font-medium text-ink">{{ $article->author?->name ?? config('shop.full_name') }}</span>
                    <span aria-hidden="true">·</span>
                    @if ($article->published_at)
                        <time datetime="{{ $article->published_at->toAtomString() }}">{{ $article->published_at->translatedFormat('j F Y') }}</time>
                        <span aria-hidden="true">·</span>
                    @endif
                    <span>{{ $article->reading_minutes }} menit baca</span>
                </p>
            </div>
        </header>

        @if ($article->coverUrl())
            <figure class="shop-container mt-8 sm:mt-10">
                <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" fetchpriority="high" decoding="async"
                     class="mx-auto aspect-[16/9] w-full max-w-5xl rounded-xl object-cover">
            </figure>
        @endif

        <div class="shop-container mt-10 sm:mt-12">
            <div class="mx-auto max-w-3xl">
                <div class="article-prose">{!! $article->body !!}</div>

                <footer class="mt-12 space-y-6 border-t border-stone-200 pt-6">
                    @if ($article->tagList())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($article->tagList() as $tag)
                                <a href="{{ route('articles.index', ['tag' => $tag]) }}" class="rounded-full bg-stone-100 px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-ink hover:text-white">#{{ $tag }}</a>
                            @endforeach
                        </div>
                    @endif
                    @include('shop.articles.partials.share')
                </footer>
            </div>
        </div>
    </article>

    @if ($article->products->isNotEmpty())
        <section class="shop-container mt-16 lg:mt-20" aria-labelledby="produk-terkait">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow text-brand-600">Dibahas di artikel ini</p>
                    <h2 id="produk-terkait" class="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">Produk terkait</h2>
                </div>
                <a href="{{ route('products.index') }}" class="link-underline shrink-0 text-sm font-semibold">Semua produk</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-x-2 gap-y-3 sm:gap-x-4 sm:gap-y-5 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($article->products as $product)
                    <x-product-tile :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="shop-container mt-16 border-t border-stone-200 pt-12 lg:mt-20" aria-labelledby="artikel-lain">
            <div class="flex items-end justify-between gap-4">
                <h2 id="artikel-lain" class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Baca juga</h2>
                <a href="{{ route('articles.index') }}" class="link-underline shrink-0 text-sm font-semibold">Semua artikel</a>
            </div>
            <div class="mt-8 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <x-article-card :article="$item" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.shop>
