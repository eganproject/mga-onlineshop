@props(['label', 'value', 'icon', 'tone' => 'brand', 'hint' => null])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'violet' => 'bg-violet-50 text-violet-600',
    ];
@endphp
<div class="card p-5">
    <div class="flex items-start justify-between">
        <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
        <span class="grid size-10 place-items-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="size-5" />
        </span>
    </div>
    <p class="mt-2 text-2xl font-extrabold tracking-tight text-stone-900">{{ $value }}</p>
    @if ($hint)
        <div class="mt-1 text-xs text-stone-500">{{ $hint }}</div>
    @endif
</div>
