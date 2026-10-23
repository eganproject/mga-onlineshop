{{--
    Pemilih tanggal (flatpickr, bahasa Indonesia). Nilai terkirim "Y-m-d",
    atau "Y-m-d\TH:i" bila :time="true" — sama dengan input date/datetime-local.
    <x-form.date name="starts_at" label="Mulai" :value="$voucher->starts_at" time optional />
    Isian opsional (tanpa required) punya tombol kosongkan.
--}}
@use('App\Support\FormField')
@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'hint' => null,
    'optional' => false,
    'value' => null,
    'time' => false,
    'placeholder' => null,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $key = FormField::errorKey($name);
    $invalid = FormField::hasError($name, $bag);
    $format = $time ? 'Y-m-d\TH:i' : 'Y-m-d';
    $value = $value instanceof \DateTimeInterface ? $value->format($format) : $value;
    $value = $key ? old($key, $value) : $value;
    $placeholder ??= $time ? 'Pilih tanggal & jam' : 'Pilih tanggal';
@endphp
<x-form.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional" :bag="$bag" :class="$wrapperClass">
    <div class="relative" x-data>
        <x-icon name="calendar" class="pointer-events-none absolute top-1/2 left-3.5 z-[1] size-4 -translate-y-1/2 text-stone-400" />
        <input type="text" {{ $time ? 'x-datepicker.time' : 'x-datepicker' }} autocomplete="off"
               @if ($id) id="{{ $id }}" @endif
               @if ($name) name="{{ $name }}" @endif
               value="{{ $value }}" placeholder="{{ $placeholder }}"
               @if ($invalid) aria-invalid="true" @endif
               {{ $attributes->class(['input pr-9 pl-10', 'input-error' => $invalid]) }}>
        @unless ($attributes->has('required'))
            <button type="button" data-date-clear class="absolute top-1/2 right-2 hidden -translate-y-1/2 rounded-lg p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" aria-label="Kosongkan tanggal">
                <x-icon name="x" class="size-4" />
            </button>
        @endunless
    </div>
</x-form.field>
