{{--
    Baris ringkas rating & jumlah terjual di kartu produk: "★ 4,8 (12) · 35 terjual".
    Tidak tampil sama sekali bila produk belum punya ulasan maupun penjualan.
--}}
@props(['product'])
@if ($product->hasReviews() || $product->sold_count > 0)
    <p {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-1.5 text-xs text-stone-500']) }}>
        @if ($product->hasReviews())
            <span class="inline-flex items-center gap-1" aria-label="Rating {{ number_format($product->rating_avg, 1, ',', '.') }} dari 5, {{ $product->review_count }} ulasan">
                <x-icon name="star" class="size-3.5 fill-amber-400 text-amber-400" />
                <span class="font-semibold text-ink">{{ number_format($product->rating_avg, 1, ',', '.') }}</span>
                <span>({{ $product->review_count }})</span>
            </span>
        @endif
        @if ($product->hasReviews() && $product->sold_count > 0)
            <span aria-hidden="true" class="text-stone-300">|</span>
        @endif
        @if ($product->sold_count > 0)
            <span>{{ $product->soldLabel() }} terjual</span>
        @endif
    </p>
@endif
