{{-- Kartu artikel untuk daftar artikel & beranda. --}}
@props(['article', 'eager' => false, 'large' => false])
<article {{ $attributes->class(['group relative flex flex-col', 'lg:grid lg:grid-cols-12 lg:items-center lg:gap-10' => $large]) }}>
    <div @class(['relative overflow-hidden rounded-lg bg-sand-100', 'aspect-[16/10]' => ! $large, 'aspect-[16/10] lg:col-span-7 lg:aspect-[16/9]' => $large])>
        @if ($article->coverUrl())
            <img src="{{ $article->coverUrl() }}" alt="" @unless ($eager) loading="lazy" @endunless decoding="async"
                 class="size-full object-cover transition duration-700 group-hover:scale-[1.03]">
        @else
            <div class="grid size-full place-items-center bg-gradient-to-br from-brand-50 to-sand-100">
                <x-logo variant="mark" class="h-12 opacity-30 grayscale" aria-hidden="true" />
            </div>
        @endif
    </div>
    <div @class(['flex flex-1 flex-col pt-4', 'lg:col-span-5 lg:pt-0' => $large])>
        <p class="flex flex-wrap items-center gap-x-2 text-xs text-stone-500">
            @if ($article->category)
                <span class="font-semibold tracking-wide text-brand-700 uppercase">{{ $article->category->name }}</span>
                <span aria-hidden="true">·</span>
            @endif
            <time datetime="{{ $article->published_at?->toAtomString() }}">{{ $article->published_at?->translatedFormat('j M Y') }}</time>
            <span aria-hidden="true">·</span>
            <span>{{ $article->reading_minutes }} menit baca</span>
        </p>
        <h3 @class([
            'mt-2 font-semibold tracking-tight text-ink text-balance transition group-hover:text-brand-700',
            'text-lg leading-snug sm:text-xl' => ! $large,
            'text-2xl leading-tight sm:text-3xl lg:text-4xl' => $large,
        ])>
            <a href="{{ route('articles.show', $article) }}" class="after:absolute after:inset-0">{{ $article->title }}</a>
        </h3>
        <p @class(['mt-2 text-sm leading-relaxed text-stone-600', 'line-clamp-2' => ! $large, 'line-clamp-3 sm:text-base' => $large])>{{ $article->summary($large ? 220 : 140) }}</p>
        @if ($large)
            <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-ink">Baca selengkapnya <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></span>
        @endif
    </div>
</article>
