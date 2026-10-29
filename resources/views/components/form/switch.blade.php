{{--
    Saklar on/off dengan judul + keterangan (Aktif, Tampilkan di beranda, Sinkron otomatis, ...).
    <x-form.switch name="is_active" label="Aktif" description="Tampil dan bisa dibeli" :checked="$product->is_active" />
    <x-form.switch name="tax_enabled" label="Kenakan PPN" card x-model="tax" tooltip="..." />
    - Selalu mengirim nilai: dicentang = "1", mati = "0" (input hidden), jadi old() benar setelah error validasi.
      Controller cukup $request->boolean('name').
    - `card` = berbingkai (untuk saklar penting di tengah form). Atribut lain (x-model, @change, disabled) diteruskan ke checkbox.
--}}
@use('App\Support\FormField')
@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'description' => null,
    'tooltip' => null,
    'tooltipTips' => null,
    'checked' => false,
    'card' => false,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $key = FormField::errorKey($name);
    $isChecked = $key && ! $attributes->has('x-model') ? (bool) old($key, $checked) : (bool) $checked;
@endphp
<div @class([$wrapperClass, 'rounded-xl border border-stone-200 p-4 transition has-[:checked]:border-brand-200 has-[:checked]:bg-brand-50/40' => $card])>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <label for="{{ $id }}" class="inline cursor-pointer text-sm font-semibold text-stone-800">{{ $label }}{{ $labelSlot ?? '' }}</label>
            @if ($tooltip || $tooltipTips)
                <x-tooltip :text="$tooltip" :tips="$tooltipTips" :label="$label" class="ml-0.5 align-middle" />
            @endif
            @if ($description || $slot->isNotEmpty())
                <p class="mt-0.5 text-xs leading-normal text-stone-500">{{ $description }}{{ $slot }}</p>
            @endif
        </div>
        @if ($name)
            <input type="hidden" name="{{ $name }}" value="0" {{ $attributes->only(['disabled', ':disabled', 'x-bind:disabled']) }}>
        @endif
        <input type="checkbox" role="switch" id="{{ $id }}" @if ($name) name="{{ $name }}" @endif value="1"
               @checked($isChecked) {{ $attributes->except(['class'])->class(['switch mt-0.5']) }}>
    </div>
    @if ($key)
        <x-input-error :for="$key" :bag="$bag" />
    @endif
</div>
