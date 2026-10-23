{{--
    Rentang tanggal dalam satu kalender; terkirim sebagai dua isian terpisah.
    <x-form.date-range from="from" to="to" :from-value="request('from')" :to-value="request('to')" label="Tanggal" />
--}}
@use('App\Support\FormField')
@props([
    'from' => 'from',
    'to' => 'to',
    'fromValue' => null,
    'toValue' => null,
    'id' => null,
    'label' => null,
    'hint' => null,
    'optional' => false,
    'placeholder' => 'Pilih rentang tanggal',
    'min' => null,
    'max' => null,
    'clearable' => true,
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($from).'_range';
    $fmt = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
    $invalid = FormField::hasError($from) || FormField::hasError($to);
@endphp
<x-form.field :label="$label" :for="$id" :name="$from" :hint="$hint" :optional="$optional" :class="$wrapperClass">
    <div class="relative" x-data x-daterange>
        <x-icon name="calendar" class="pointer-events-none absolute top-1/2 left-3.5 z-[1] size-4 -translate-y-1/2 text-stone-400" />
        <input type="text" data-range-display id="{{ $id }}" placeholder="{{ $placeholder }}" autocomplete="off"
               @if ($min) min="{{ $fmt($min) }}" @endif
               @if ($max) max="{{ $fmt($max) }}" @endif
               {{ $attributes->class(['input pr-9 pl-10', 'input-error' => $invalid]) }}>
        <input type="hidden" data-range-from name="{{ $from }}" value="{{ $fmt(old(FormField::errorKey($from), $fromValue)) }}">
        <input type="hidden" data-range-to name="{{ $to }}" value="{{ $fmt(old(FormField::errorKey($to), $toValue)) }}">
        @if ($clearable)
            <button type="button" data-date-clear class="absolute top-1/2 right-2 hidden -translate-y-1/2 rounded-lg p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" aria-label="Kosongkan tanggal">
                <x-icon name="x" class="size-4" />
            </button>
        @endif
    </div>
    @if ($to !== $from)
        <x-slot:after><x-input-error :for="FormField::errorKey($to)" /></x-slot:after>
    @endif
</x-form.field>
