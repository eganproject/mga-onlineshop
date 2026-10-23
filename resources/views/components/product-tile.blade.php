{{--
    Kartu produk gaya "tile" untuk beranda: gambar di kotak abu-abu, hati
    wishlist di pojok kanan bawah gambar, nama & harga di bawahnya.
--}}
@props(['product', 'eager' => false])
<article {{ $attributes->merge(['class' => 'group relative flex flex-col rounded-lg transition-colors hover:bg-stone-100']) }}>
    <div class="relative">
        <x-product-image :product="$product" :eager="$eager" bg="bg-stone-200/70" class="aspect-square rounded-lg" />

        <div class="pointer-events-none absolute left-2.5 top-2.5 flex flex-col items-start gap-1.5 sm:left-3 sm:top-3">
            @if ($product->isOnSale())
                <span class="rounded bg-accent-500 px-2 py-1 text-[10px] font-bold tracking-wider text-white uppercase">-{{ $product->discountPercent() }}%</span>
            @endif
            @unless ($product->inStock())
                <span class="rounded bg-white px-2 py-1 text-[10px] font-bold tracking-wider text-ink uppercase">Stok habis</span>
            @endunless
        </div>

        <div class="absolute right-1 bottom-1 z-10 sm:right-2 sm:bottom-2">
            <x-wishlist-button :product="$product" variant="plain" />
        </div>
    </div>

    <div class="flex flex-1 flex-col px-2 pt-2.5 pb-3">
        <h3 class="text-[13px] leading-snug font-medium text-ink line-clamp-2 sm:text-sm">
            <a href="{{ route('products.show', $product) }}" class="after:absolute after:inset-0">{{ $product->name }}</a>
        </h3>
        <div class="mt-1 flex flex-wrap items-baseline gap-x-2">
            <p @class(['text-[13px] sm:text-sm', 'font-semibold text-accent-700' => $product->isOnSale(), 'text-brand-700' => ! $product->isOnSale()])>@rupiah($product->price)</p>
            @if ($product->isOnSale())
                <p class="text-xs text-stone-400 line-through">@rupiah($product->compare_at_price)</p>
            @endif
        </div>
    </div>
</article>
