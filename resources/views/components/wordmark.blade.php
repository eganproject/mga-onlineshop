@props(['dark' => false, 'size' => 'md'])
<span {{ $attributes->merge(['class' => 'inline-flex flex-col items-center leading-none']) }}>
    <span @class([
        'font-display font-black tracking-[-0.04em] uppercase',
        'text-white' => $dark,
        'text-ink' => ! $dark,
        'text-2xl sm:text-[28px]' => $size === 'md',
        'text-4xl' => $size === 'lg',
    ])>{{ config('shop.name') }}</span>
    <span @class([
        'mt-0.5 text-[8px] font-semibold tracking-[0.32em] uppercase sm:text-[9px]',
        'text-white/60' => $dark,
        'text-stone-500' => ! $dark,
    ])>{{ config('shop.full_name') }}</span>
</span>
