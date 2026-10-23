{{--
    Judul kolom yang bisa diklik untuk mengurutkan (?sort=kolom / ?sort=-kolom).
    Kolom aktif dibaca dari AdminList::sort(). `desc` = urutan pertama saat diklik turun (angka/tanggal).
--}}
@props(['column', 'desc' => false])
@php
    $active = (string) request()->attributes->get('admin_sort', '');
    $isActive = ltrim($active, '-') === $column;
    $isDesc = $isActive && str_starts_with($active, '-');
    $next = $isActive ? ($isDesc ? $column : '-'.$column) : ($desc ? '-'.$column : $column);
    $url = request()->fullUrlWithQuery(['sort' => $next, 'page' => null]);
@endphp
<a href="{{ $url }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-1 whitespace-nowrap hover:text-stone-900'.($isActive ? ' text-stone-900' : '')]) }}
   title="Urutkan">
    {{ $slot }}
    @if ($isActive)
        <x-icon :name="$isDesc ? 'chevron-down' : 'chevron-up'" class="size-3.5 text-brand-600" />
    @else
        <x-icon name="chevron-up-down" class="size-3.5 text-stone-300 group-hover:text-stone-500" />
    @endif
</a>
