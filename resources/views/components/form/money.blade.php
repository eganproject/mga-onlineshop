{{--
    Input nominal Rupiah: tampil "1.250.000", terkirim "1250000".
    <x-form.money name="price" label="Harga jual" :value="$product->price" required />
    Bisa juga langsung di input biasa: <input x-money ...> (butuh scope Alpine).
--}}
@use('App\Support\FormField')
@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'hint' => null,
    'optional' => false,
    'value' => null,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $key = FormField::errorKey($name);
    $invalid = FormField::hasError($name, $bag);
    $value = FormField::thousands($key ? old($key, $value) : $value);
@endphp
<x-form.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional" :bag="$bag" :class="$wrapperClass">
    <div class="relative" x-data>
        <span class="input-addon">Rp</span>
        <input type="text" x-money inputmode="numeric" autocomplete="off"
               @if ($id) id="{{ $id }}" @endif
               @if ($name) name="{{ $name }}" @endif
               @if (! $attributes->has('x-model')) value="{{ $value }}" @endif
               @if ($invalid) aria-invalid="true" @endif
               {{ $attributes->class(['input pl-10 tabular-nums', 'input-error' => $invalid]) }}>
    </div>
    @isset($after)
        <x-slot:after>{{ $after }}</x-slot:after>
    @endisset
</x-form.field>
