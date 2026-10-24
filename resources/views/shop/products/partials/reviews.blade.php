{{-- Ulasan & rating produk. Variabel: $product, $reviews, $ratingDistribution, $myReview, $canReview --}}
<section id="ulasan" class="mt-20 scroll-mt-28 border-t border-stone-200 pt-12 lg:mt-28 lg:pt-16">
    <div class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-14">
        {{-- Ringkasan --}}
        <div class="lg:col-span-4">
            <h2 class="display text-3xl leading-none sm:text-5xl">Ulasan</h2>
            @if ($product->hasReviews())
                <div class="mt-6 flex items-end gap-4">
                    <p class="font-display text-6xl leading-none font-extrabold tracking-tight">{{ number_format($product->rating_avg, 1, ',', '.') }}</p>
                    <div class="pb-1">
                        <x-star-rating :rating="$product->rating_avg" size="size-5" />
                        <p class="mt-1 text-sm text-stone-500">dari {{ $product->review_count }} ulasan</p>
                    </div>
                </div>
                <ul class="mt-6 space-y-2">
                    @foreach ($ratingDistribution as $star => $count)
                        @php($percent = $product->review_count ? round($count / $product->review_count * 100) : 0)
                        <li class="flex items-center gap-3 text-xs">
                            <span class="flex w-8 items-center gap-1 font-semibold">{{ $star }} <x-icon name="star" class="size-3 fill-ink text-ink" /></span>
                            <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-sand-100">
                                <span class="block h-full rounded-full bg-ink" style="width: {{ $percent }}%"></span>
                            </span>
                            <span class="w-8 text-right text-stone-500">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-6 text-sm text-stone-500">Belum ada ulasan untuk produk ini.</p>
            @endif

            {{-- Form / ajakan menulis ulasan --}}
            <div id="tulis-ulasan" class="mt-8 scroll-mt-28">
                @if ($canReview)
                    @php($reviewErrors = $errors->getBag('review'))
                    <form method="POST" action="{{ route('reviews.store', $product) }}" class="bg-sand-50 p-5"
                          x-data="{ rating: {{ (int) old('rating', $myReview?->rating ?? 0) }}, hover: 0 }">
                        @csrf
                        <p class="text-sm font-semibold">{{ $myReview ? 'Edit ulasanmu' : 'Tulis ulasan' }}</p>
                        @if ($myReview?->status === \App\Enums\ReviewStatus::Pending)
                            <p class="mt-1 text-xs text-amber-700">Ulasanmu sedang menunggu moderasi.</p>
                        @elseif ($myReview?->status === \App\Enums\ReviewStatus::Rejected)
                            <p class="mt-1 text-xs text-rose-700">Ulasanmu sebelumnya tidak ditayangkan. Silakan perbarui.</p>
                        @endif

                        <fieldset class="mt-4">
                            <legend class="label">Rating</legend>
                            <div class="flex gap-1" @mouseleave="hover = 0">
                                @for ($i = 1; $i <= 5; $i++)
                                    <label class="cursor-pointer" @mouseenter="hover = {{ $i }}">
                                        <input type="radio" name="rating" value="{{ $i }}" x-model.number="rating" class="peer sr-only" @checked(old('rating', $myReview?->rating) == $i) required>
                                        <span class="sr-only">{{ $i }} bintang</span>
                                        <x-icon name="star" class="size-8 text-amber-400 transition peer-focus-visible:scale-110" x-bind:class="{ 'fill-amber-400': (hover || rating) >= {{ $i }} }" />
                                    </label>
                                @endfor
                            </div>
                            @if ($reviewErrors->has('rating'))<p class="mt-1 text-xs text-rose-600">{{ $reviewErrors->first('rating') }}</p>@endif
                        </fieldset>

                        <div class="mt-4">
                            <label for="review-title" class="label">Judul <span class="font-normal text-stone-400">(opsional)</span></label>
                            <input id="review-title" name="title" maxlength="120" value="{{ old('title', $myReview?->title) }}" class="input" placeholder="Ringkas pengalamanmu">
                        </div>
                        <x-form.textarea id="review-body" name="body" label="Ulasan" bag="review" wrapper-class="mt-4" :value="$myReview?->body"
                                         rows="4" maxlength="2000" required minlength="10" placeholder="Bagaimana kualitas, kecocokan, dan pengirimannya?" />
                        <button class="btn btn-ink btn-pill mt-5 w-full">{{ $myReview ? 'Perbarui ulasan' : 'Kirim ulasan' }}</button>
                    </form>
                @elseif (! auth()->check())
                    <p class="text-sm text-stone-500">Sudah beli produk ini? <a href="{{ route('login') }}" class="link-underline font-semibold text-ink">Masuk</a> untuk menulis ulasan.</p>
                @elseif (auth()->user()->isCustomer())
                    <p class="text-sm text-stone-500">Ulasan bisa ditulis setelah pesanan berisi produk ini selesai.</p>
                @endif
            </div>
        </div>

        {{-- Daftar ulasan --}}
        <div class="lg:col-span-8">
            @if ($reviews->isNotEmpty())
            @fragment('infinite-list')
            <x-infinite-list :paginator="$reviews" label="ulasan" skeleton="review" :skeleton-count="2" :auto="false" more="Lihat ulasan lainnya">
            @foreach ($reviews as $review)
                <article class="border-b border-stone-200 py-6 first:pt-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-star-rating :rating="$review->rating" />
                        <time datetime="{{ ($review->approved_at ?? $review->created_at)->toDateString() }}" class="text-xs text-stone-500">{{ ($review->approved_at ?? $review->created_at)->translatedFormat('d M Y') }}</time>
                    </div>
                    @if ($review->title)
                        <h3 class="mt-3 font-semibold">{{ $review->title }}</h3>
                    @endif
                    <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-stone-600">{{ $review->body }}</p>
                    <p class="mt-3 flex items-center gap-2 text-xs">
                        <span class="font-semibold">{{ $review->authorName() }}</span>
                        @if ($review->order_id)
                            <span class="inline-flex items-center gap-1 text-emerald-700"><x-icon name="check-circle" class="size-3.5" /> Pembeli terverifikasi</span>
                        @endif
                    </p>
                    @if ($review->admin_reply)
                        <div class="mt-4 border-l-2 border-ink bg-sand-50 p-4 text-sm">
                            <p class="text-xs font-semibold tracking-wide uppercase">Balasan {{ config('shop.name') }}</p>
                            <p class="mt-1 whitespace-pre-line text-stone-600">{{ $review->admin_reply }}</p>
                        </div>
                    @endif
                </article>
            @endforeach
            </x-infinite-list>
            @endfragment
            @else
                <div class="bg-sand-50 p-8 text-center">
                    <x-icon name="star" class="mx-auto size-8 text-stone-400" />
                    <p class="mt-3 text-sm font-semibold">Jadilah yang pertama mengulas</p>
                    <p class="mt-1 text-sm text-stone-500">Ulasan ditulis oleh pembeli yang pesanannya sudah selesai.</p>
                </div>
            @endif
        </div>
    </div>
</section>
