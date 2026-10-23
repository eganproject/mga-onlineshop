@props(['product', 'class' => 'aspect-square'])
<div {{ $attributes->merge(['class' => 'relative overflow-hidden bg-gradient-to-br from-stone-100 to-brand-50 '.$class]) }}>
    @if ($url = $product->imageUrl())
        <img src="{{ $url }}" alt="{{ $product->name }}" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
    @else
        <div class="grid size-full place-items-center text-brand-300">
            <div class="flex flex-col items-center gap-1">
                <x-icon name="photo" class="size-10" />
                <span class="px-3 text-center text-[11px] font-medium text-stone-400 line-clamp-1">{{ $product->name }}</span>
            </div>
        </div>
    @endif
</div>
