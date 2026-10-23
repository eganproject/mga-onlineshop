@props(['dark' => false, 'compact' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-extrabold tracking-tight text-white shadow-md shadow-brand-600/25">
        M
    </span>
    @unless ($compact)
        <span class="leading-tight">
            <span @class(['block text-base font-extrabold tracking-tight', 'text-white' => $dark, 'text-stone-900' => ! $dark])>{{ config('shop.name') }}</span>
            <span @class(['block text-[11px] font-medium', 'text-white/70' => $dark, 'text-stone-500' => ! $dark])>{{ config('shop.full_name') }}</span>
        </span>
    @endunless
</span>
