{{-- Beranda: 3 artikel terbaru (tidak tampil bila belum ada artikel terbit). --}}
@if ($latestArticles->isNotEmpty())
    <section class="shop-container mt-16 lg:mt-24" aria-labelledby="artikel-terbaru">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="eyebrow text-brand-600">Tips & panduan</p>
                <h2 id="artikel-terbaru" class="display mt-2 text-3xl leading-none sm:text-5xl">Artikel terbaru</h2>
            </div>
            <a href="{{ route('articles.index') }}" class="link-underline shrink-0 text-sm font-semibold">Semua artikel</a>
        </div>
        <div class="no-scrollbar -mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-x-6 sm:gap-y-10 sm:overflow-visible sm:px-0 lg:grid-cols-3">
            @foreach ($latestArticles as $article)
                <x-article-card :article="$article" class="w-[82%] shrink-0 snap-start sm:w-auto" />
            @endforeach
        </div>
    </section>
@endif
