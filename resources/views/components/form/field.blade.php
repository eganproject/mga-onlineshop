{{--
    Pembungkus isian: label, kontrol (slot), petunjuk, dan pesan error.
    <x-form.field label="Nama" for="name" name="name" hint="..." optional>
        <input id="name" name="name" class="input">
    </x-form.field>
    `name` dipakai untuk mencari error (boleh "variants[0][price]" atau "variants.0.price").
    Tooltip (?) di samping label untuk isian yang fungsinya kurang jelas:
    tooltip="Fungsi isian" :tooltip-tips="['Saran agar hasil maksimal', '...']" (lihat <x-tooltip>).
--}}
@props([
    'label' => null,
    'for' => null,
    'name' => null,
    'hint' => null,
    'optional' => false,
    'tooltip' => null,
    'tooltipTips' => null,
    'bag' => 'default',
])
@php
    $errorKey = \App\Support\FormField::errorKey($name ?? $for);
@endphp
<div {{ $attributes }}>
    @if ($label || isset($labelSlot))
        @if ($tooltip || $tooltipTips)
            <div class="label-row">
                <label @if ($for) for="{{ $for }}" @endif class="label">
                    {{ $label }}{{ $labelSlot ?? '' }}
                    @if ($optional)
                        <span class="font-normal text-stone-400">({{ is_string($optional) ? $optional : 'opsional' }})</span>
                    @endif
                </label>
                <x-tooltip :text="$tooltip" :tips="$tooltipTips" :label="$label" />
            </div>
        @else
            <label @if ($for) for="{{ $for }}" @endif class="label">
                {{ $label }}{{ $labelSlot ?? '' }}
                @if ($optional)
                    <span class="font-normal text-stone-400">({{ is_string($optional) ? $optional : 'opsional' }})</span>
                @endif
            </label>
        @endif
    @endif
    {{ $slot }}
    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    {{ $after ?? '' }}
    @if ($errorKey)
        <x-input-error :for="$errorKey" :bag="$bag" />
    @endif
</div>
