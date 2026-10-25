@props(['rating' => 0, 'size' => 'size-4'])
{{-- Bintang 1–5 dengan isi sebagian (mis. 4,7 = empat penuh + 70% bintang kelima). --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5']) }} role="img" aria-label="Rating {{ number_format($rating, 1, ',', '.') }} dari 5">
    @for ($i = 1; $i <= 5; $i++)
        @php($fill = max(0, min(1, (float) $rating - ($i - 1))))
        <span class="relative inline-flex shrink-0">
            <x-icon name="star" @class([$size, 'text-stone-300']) />
            @if ($fill > 0)
                <span class="absolute inset-y-0 left-0 overflow-hidden" style="width: {{ round($fill * 100) }}%">
                    <x-icon name="star" @class([$size, 'max-w-none fill-amber-400 text-amber-400']) />
                </span>
            @endif
        </span>
    @endfor
</span>
