@props(['for', 'bag' => 'default'])
@error($for, $bag)
    <p data-field-error {{ $attributes->merge(['class' => 'field-error']) }}>
        <x-icon name="warning" class="mt-px size-3.5 shrink-0" />
        <span>{{ $message }}</span>
    </p>
@enderror
