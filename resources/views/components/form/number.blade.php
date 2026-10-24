{{--
    Input angka dengan tombol − / + (stok, berat, kuota, urutan, ...).
    <x-form.number name="stock" label="Stok" :value="$product->stock" min="0" suffix="pcs" />
    Atribut min/max/step diteruskan ke <input type="number">; x-model juga bisa.
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
    'suffix' => null,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $key = FormField::errorKey($name);
    $invalid = FormField::hasError($name, $bag);
    $value = $key && ! $attributes->has('x-model') ? old($key, $value) : $value;
    $step = "const n = \$refs.number; if (n.disabled || n.readOnly) return; %s; n.dispatchEvent(new Event('input', { bubbles: true })); n.dispatchEvent(new Event('change', { bubbles: true }))";
    $button = 'grid w-10 shrink-0 place-items-center text-stone-500 transition hover:bg-stone-50 hover:text-brand-700 disabled:opacity-40';
@endphp
<x-form.field :label="$label" :for="$id" :name="$name" :hint="$hint" :tooltip="$tooltip" :tooltip-tips="$tooltipTips" :optional="$optional" :bag="$bag" :class="$wrapperClass">
    <div x-data @class([
        'flex items-stretch overflow-hidden rounded-xl border bg-white shadow-xs transition focus-within:ring-4 has-[:disabled]:bg-stone-50 has-[:disabled]:text-stone-400',
        'border-stone-200 focus-within:border-brand-500 focus-within:ring-brand-500/15' => ! $invalid,
        'border-rose-300 focus-within:border-rose-500 focus-within:ring-rose-500/15' => $invalid,
    ])>
        <button type="button" tabindex="-1" class="{{ $button }} border-r border-stone-100" aria-label="Kurangi" @click="{{ sprintf($step, 'n.stepDown()') }}">
            <x-icon name="minus" class="size-4" />
        </button>
        <input type="number" x-ref="number" inputmode="numeric"
               @if ($id) id="{{ $id }}" @endif
               @if ($name) name="{{ $name }}" @endif
               @if (! $attributes->has('x-model')) value="{{ $value }}" @endif
               @if ($invalid) aria-invalid="true" @endif
               {{ $attributes->class(['no-spinner block w-full min-w-0 border-0 bg-transparent px-2 py-2.5 text-center text-sm font-medium text-stone-900 tabular-nums placeholder:font-normal placeholder:text-stone-400 focus:ring-0']) }}>
        @if ($suffix)
            <span class="flex items-center pr-2 text-xs font-medium text-stone-400">{{ $suffix }}</span>
        @endif
        <button type="button" tabindex="-1" class="{{ $button }} border-l border-stone-100" aria-label="Tambah" @click="{{ sprintf($step, 'n.stepUp()') }}">
            <x-icon name="plus" class="size-4" />
        </button>
    </div>
</x-form.field>
