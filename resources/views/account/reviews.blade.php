<x-layouts.account title="Ulasan Saya" heading="Ulasan">
    @if ($toReview->isNotEmpty())
        <section class="mb-10">
            <h3 class="eyebrow text-stone-500">Menunggu ulasanmu</h3>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($toReview as $product)
                    <li class="card flex items-center gap-4 p-3">
                        <x-product-image :product="$product" class="size-16 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $product->name }}</p>
                            <a href="{{ route('products.show', $product) }}#tulis-ulasan" class="link-underline mt-1 inline-block text-xs font-semibold">Tulis ulasan</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section>
        <h3 class="eyebrow text-stone-500">Ulasan yang sudah kamu tulis</h3>
        @forelse ($reviews as $review)
            <article class="card mt-4 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if ($review->product)
                            <a href="{{ route('products.show', $review->product) }}#ulasan" class="text-sm font-semibold hover:underline">{{ $review->product->name }}</a>
                        @endif
                        <div class="mt-1 flex items-center gap-2">
                            <x-star-rating :rating="$review->rating" />
                            <span class="text-xs text-stone-500">{{ $review->updated_at->translatedFormat('d M Y') }}</span>
                        </div>
                    </div>
                    <span class="badge ring-1 ring-inset {{ $review->status->badgeClasses() }}">{{ $review->status->label() }}</span>
                </div>
                @if ($review->title)<p class="mt-3 text-sm font-semibold">{{ $review->title }}</p>@endif
                <p class="mt-1 whitespace-pre-line text-sm text-stone-600">{{ $review->body }}</p>
                @if ($review->admin_reply)
                    <div class="mt-3 border-l-2 border-ink bg-sand-50 p-3 text-sm">
                        <p class="text-xs font-semibold">Balasan {{ config('shop.name') }}</p>
                        <p class="mt-1 whitespace-pre-line text-stone-600">{{ $review->admin_reply }}</p>
                    </div>
                @endif
            </article>
        @empty
            <div class="card mt-4">
                <x-empty-state icon="star" title="Belum ada ulasan" description="Setelah pesananmu selesai, kamu bisa menilai produk yang dibeli." />
            </div>
        @endforelse
    </section>
</x-layouts.account>
