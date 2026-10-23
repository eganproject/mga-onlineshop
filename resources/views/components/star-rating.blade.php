@props(['rating' => 0, 'size' => 'size-4'])
{{-- Bintang 1–5, nilai rating dibulatkan ke bilangan bulat terdekat. --}}
@php($filled = (int) round($rating))
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5']) }} role="img" aria-label="Rating {{ number_format($rating, 1, ',', '.') }} dari 5">
    @for ($i = 1; $i <= 5; $i++)
        <x-icon name="star" @class([$size, 'fill-amber-400 text-amber-400' => $i <= $filled, 'text-stone-300' => $i > $filled]) />
    @endfor
</span>
