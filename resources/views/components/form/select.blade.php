{{--
    Select yang bisa dicari (Tom Select). Kotak pencarian muncul otomatis bila
    opsinya banyak; paksa dengan :search="true|false".
    <x-form.select name="courier" label="Kurir" :options="['jne' => 'JNE']" :selected="..." placeholder="Semua kurir" />
    :options = [value => label] atau [label grup => [value => label]] untuk optgroup.
    Opsi juga boleh ditulis manual di slot (<option>...).
--}}
@use('App\Support\FormField')
@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'hint' => null,
    'optional' => false,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'search' => null,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $key = FormField::errorKey($name);
    $invalid = FormField::hasError($name, $bag);
    $selected = $key ? old($key, $selected) : $selected;
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;
    $isSelected = fn ($value) => (string) $value === (string) $selected;
    $config = array_filter(['search' => $search], fn ($v) => $v !== null);
@endphp
<x-form.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional" :bag="$bag" :class="$wrapperClass">
    <select x-data x-searchable="@js($config)"
            @if ($id) id="{{ $id }}" @endif
            @if ($name) name="{{ $name }}" @endif
            @if ($invalid) aria-invalid="true" @endif
            {{ $attributes->class(['input', 'input-error' => $invalid]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            @if (is_iterable($text))
                <optgroup label="{{ $value }}">
                    @foreach ($text as $v => $t)
                        <option value="{{ $v }}" @selected($isSelected($v))>{{ $t }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $value }}" @selected($isSelected($value))>{{ $text }}</option>
            @endif
        @endforeach
        {{ $slot }}
    </select>
</x-form.field>
