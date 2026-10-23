{{-- Logo lengkap MGA untuk header/halaman publik. Lihat <x-logo>. --}}
@props(['dark' => false, 'size' => 'md'])
<x-logo :dark="$dark" {{ $attributes->class([
    'h-10 lg:h-12' => $size === 'md',
    'h-14 sm:h-16' => $size === 'lg',
]) }} />
