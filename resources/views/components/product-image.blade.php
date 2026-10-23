@props(['product', 'class' => 'aspect-square', 'eager' => false, 'bg' => 'bg-sand-100'])
<div {{ $attributes->merge(['class' => 'relative overflow-hidden '.$bg.' '.$class]) }}>
    @if ($url = $product->imageUrl())
        <img src="{{ $url }}" alt="{{ $product->name }}" @unless ($eager) loading="lazy" @endunless decoding="async"
             class="size-full object-cover transition duration-700 ease-out group-hover:scale-[1.04]">
    @else
        {{-- Placeholder bila produk belum punya foto --}}
        <div class="grid size-full place-items-center p-4">
            <div class="flex flex-col items-center gap-2 text-center">
                <x-logo variant="mark" class="h-9 opacity-25 grayscale" aria-hidden="true" />
                <span class="max-w-[12rem] text-[11px] font-medium text-stone-400 line-clamp-2">{{ $product->name }}</span>
            </div>
        </div>
    @endif
</div>
