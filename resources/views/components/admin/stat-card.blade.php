@props(['label', 'value', 'icon', 'tone' => 'brand', 'hint' => null, 'change' => false, 'changeLabel' => 'vs periode sebelumnya'])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'amber' => 'bg-amber-50 text-amber-600',
    ];
@endphp
<div {{ $attributes->class('card min-w-0 p-4 sm:p-5') }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium text-stone-500 sm:text-sm">{{ $label }}</p>
        <span class="hidden size-10 shrink-0 sm:grid place-items-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="size-5" />
        </span>
    </div>
    <p class="mt-2 text-xl font-extrabold sm:text-2xl tracking-tight text-stone-900 tabular-nums">{{ $value }}</p>
    @if ($change !== false)
        <div class="mt-1 text-xs text-stone-500">
            @if (is_null($change))
                Belum ada data pembanding
            @else
                <span @class(['font-semibold', 'text-emerald-600' => $change > 0, 'text-rose-600' => $change < 0, 'text-stone-600' => $change == 0])>
                    {{ $change > 0 ? '▲' : ($change < 0 ? '▼' : '•') }} {{ str_replace('.', ',', (string) abs($change)) }}%
                </span> {{ $changeLabel }}
            @endif
        </div>
    @elseif ($hint)
        <div class="mt-1 text-xs text-stone-500">{{ $hint }}</div>
    @endif
</div>
