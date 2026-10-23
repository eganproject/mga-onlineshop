{{-- Slider banner promo (dikelola di Admin › Banner). --}}
@php($count = $banners->count())
<section class="shop-container mt-6 sm:mt-10" aria-roledescription="carousel" aria-label="Promo"
         x-data="bannerCarousel({ count: {{ $count }} })"
         @mouseenter="stop()" @mouseleave="play()" @focusin="stop()" @focusout="play()" @touchstart.passive="stop()">
    <div class="relative">
        <div x-ref="track" @scroll.debounce.100ms="onScroll()" class="no-scrollbar flex snap-x snap-mandatory overflow-x-auto">
            @foreach ($banners as $banner)
                @php($href = $banner->href())
                <div class="w-full shrink-0 snap-start" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} dari {{ $count }}">
                    <{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @if ($banner->isExternal()) target="_blank" rel="noopener" @endif @endif
                        class="group relative block overflow-hidden bg-sand-100">
                        <picture>
                            @if ($banner->mobileImageUrl())
                                <source media="(max-width: 639px)" srcset="{{ $banner->mobileImageUrl() }}">
                            @endif
                            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->show_text ? '' : $banner->title }}"
                                 @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                                 @class(['w-full object-cover transition duration-700 group-hover:scale-[1.02] sm:aspect-[21/9]', 'aspect-[4/5]' => $banner->mobile_image_path, 'aspect-[4/3]' => ! $banner->mobile_image_path])>
                        </picture>
                        @if ($banner->show_text)
                            <div class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/15 to-transparent sm:bg-gradient-to-r sm:from-black/60 sm:via-black/20"></div>
                            <div class="absolute inset-x-5 bottom-6 text-white sm:inset-x-10 sm:bottom-auto sm:top-1/2 sm:max-w-xl sm:-translate-y-1/2 lg:inset-x-14">
                                <p class="eyebrow text-white/80">Promo</p>
                                <h2 class="display mt-2 text-3xl leading-[0.95] sm:text-5xl lg:text-6xl">{{ $banner->title }}</h2>
                                @if ($banner->subtitle)
                                    <p class="mt-3 max-w-md text-sm text-white/85 sm:text-base">{{ $banner->subtitle }}</p>
                                @endif
                                @if ($href && $banner->button_label)
                                    <span class="btn btn-light btn-pill mt-5 sm:mt-6">{{ $banner->button_label }} <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></span>
                                @endif
                            </div>
                        @endif
                    </{{ $href ? 'a' : 'div' }}>
                </div>
            @endforeach
        </div>

        @if ($count > 1)
            <button type="button" @click="go(index - 1)" aria-label="Banner sebelumnya"
                    class="absolute left-4 top-1/2 hidden size-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow-sm backdrop-blur transition hover:bg-white sm:grid">
                <x-icon name="chevron-left" class="size-5" />
            </button>
            <button type="button" @click="go(index + 1)" aria-label="Banner berikutnya"
                    class="absolute right-4 top-1/2 hidden size-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow-sm backdrop-blur transition hover:bg-white sm:grid">
                <x-icon name="chevron-right" class="size-5" />
            </button>
        @endif
    </div>

    @if ($count > 1)
        <div class="mt-3 flex justify-center gap-1.5">
            @foreach ($banners as $banner)
                <button type="button" @click="go({{ $loop->index }})" aria-label="Tampilkan banner {{ $loop->iteration }}"
                        :aria-current="index === {{ $loop->index }}"
                        :class="index === {{ $loop->index }} ? 'w-6 bg-ink' : 'w-1.5 bg-stone-300 hover:bg-stone-400'"
                        class="h-1.5 rounded-full transition-all"></button>
            @endforeach
        </div>
    @endif
</section>
