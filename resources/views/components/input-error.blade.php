@props(['for', 'bag' => 'default'])
@error($for, $bag)
    <p {{ $attributes->merge(['class' => 'mt-1.5 text-xs font-medium text-rose-600']) }}>{{ $message }}</p>
@enderror
