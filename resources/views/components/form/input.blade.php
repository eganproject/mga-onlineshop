{{--
    Input teks serbaguna dengan label, ikon/prefix/suffix, petunjuk, dan error.
    <x-form.input name="sku" label="SKU" optional hint="..." />
    Atribut lain (required, maxlength, x-model, x-bind:name, ...) diteruskan ke <input>.
    Di dalam template Alpine pakai x-bind:name, bukan :name (":" dibaca Blade sebagai PHP).
--}}
@use('App\Support\FormField')
@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'hint' => null,
    'tooltip' => null,
    'tooltipTips' => null,
    'optional' => false,
    'value' => null,
    'type' => 'text',
    'icon' => null,
    'prefix' => null,
    'suffix' => null,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $key = FormField::errorKey($name);
    $invalid = FormField::hasError($name, $bag);
    $value = $key && ! $attributes->has('x-model') ? old($key, $value) : $value;
@endphp
<x-form.field :label="$label" :for="$id" :name="$name" :hint="$hint" :tooltip="$tooltip" :tooltip-tips="$tooltipTips" :optional="$optional" :bag="$bag" :class="$wrapperClass">
    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-stone-400" />
        @elseif ($prefix)
            <span class="input-addon">{{ $prefix }}</span>
        @endif
        <input type="{{ $type }}"
               @if ($id) id="{{ $id }}" @endif
               @if ($name) name="{{ $name }}" @endif
               @if (! $attributes->has('x-model')) value="{{ $value }}" @endif
               @if ($invalid) aria-invalid="true" @endif
               {{ $attributes->class(['input', 'pl-10' => $icon || $prefix, 'pr-14' => $suffix, 'input-error' => $invalid]) }}>
        @if ($suffix)
            <span class="input-addon right-3.5 left-auto font-medium">{{ $suffix }}</span>
        @endif
    </div>
    @isset($after)
        <x-slot:after>{{ $after }}</x-slot:after>
    @endisset
</x-form.field>
