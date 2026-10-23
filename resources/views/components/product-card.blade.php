@props(['product'])
<article class="group relative flex flex-col overflow-hidden rounded-2xl border border-stone-200/70 bg-white transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-stone-900/5">
    <a href="{{ route('products.show', $product) }}" class="block">
        <x-product-image :product="$product" />
        <div class="absolute left-3 top-3 flex flex-col gap-1.5">
            @if ($product->isOnSale())
                <span class="rounded-lg bg-rose-500 px-2 py-0.5 text-[11px] font-bold text-white">-{{ $product->discountPercent() }}%</span>
            @endif
            @unless ($product->inStock())
                <span class="rounded-lg bg-stone-900/80 px-2 py-0.5 text-[11px] font-semibold text-white">Stok habis</span>
            @endunless
        </div>
    </a>
    <div class="flex flex-1 flex-col p-3 sm:p-4">
        @if ($product->relationLoaded('category') && $product->category)
            <p class="mb-1 text-[11px] font-semibold tracking-wide text-brand-600 uppercase">{{ $product->category->name }}</p>
        @endif
        <h3 class="text-sm font-semibold text-stone-800 line-clamp-2">
            <a href="{{ route('products.show', $product) }}" class="after:absolute after:inset-0">{{ $product->name }}</a>
        </h3>
        <div class="mt-auto pt-3">
            <p class="text-base font-bold text-stone-900">@rupiah($product->price)</p>
            @if ($product->isOnSale())
                <p class="text-xs text-stone-400 line-through">@rupiah($product->compare_at_price)</p>
            @endif
        </div>
    </div>
</article>
