{{--
    Textarea dengan penghitung karakter (bila ada maxlength).
    <x-form.textarea name="notes" label="Catatan" maxlength="500" rows="3" :value="old('notes')" />
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
    'counter' => true,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $key = FormField::errorKey($name);
    $invalid = FormField::hasError($name, $bag);
    $value = $key && ! $attributes->has('x-model') ? old($key, $value) : $value;
    $max = (int) $attributes->get('maxlength');
    $counter = $counter && $max > 0;
@endphp
<x-form.field :label="$label" :for="$id" :name="$name" :hint="$hint" :tooltip="$tooltip" :tooltip-tips="$tooltipTips" :optional="$optional" :bag="$bag" :class="$wrapperClass">
    <div @if ($counter) x-data="{ count: {{ mb_strlen((string) $value) }} }" @endif>
        <textarea
            @if ($id) id="{{ $id }}" @endif
            @if ($name) name="{{ $name }}" @endif
            @if ($counter) x-on:input="count = $el.value.length" x-init="count = $el.value.length" @endif
            @if ($invalid) aria-invalid="true" @endif
            {{ $attributes->merge(['rows' => 3])->class(['input', 'input-error' => $invalid]) }}>{{ $value }}</textarea>
        @if ($counter)
            <p class="mt-1 text-right text-[11px] text-stone-400 tabular-nums" :class="count >= {{ $max }} && 'font-semibold text-rose-600'" aria-live="polite">
                <span x-text="count">{{ mb_strlen((string) $value) }}</span>/{{ $max }}
            </p>
        @endif
    </div>
</x-form.field>
