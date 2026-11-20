@props(['label', 'value', 'icon', 'tone' => 'brand', 'hint' => null, 'change' => false, 'changeLabel' => 'vs periode sebelumnya'])
{{-- Netral: ikon abu-abu kecil, warna hanya untuk naik/turun. `tone` dibiarkan demi kompatibilitas. --}}
<div {{ $attributes->class('card min-w-0 p-4') }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-medium text-stone-500 sm:text-sm">{{ $label }}</p>
        <x-icon :name="$icon" class="hidden size-4 shrink-0 text-stone-400 sm:block" />
    </div>
    <p class="mt-1.5 truncate text-lg font-semibold tracking-tight text-stone-900 tabular-nums sm:text-xl">{{ $value }}</p>
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
