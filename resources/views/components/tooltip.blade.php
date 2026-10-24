{{--
    Ikon bantuan (?) kecil dengan tooltip. Hover di desktop, ketuk di HP,
    fokus keyboard (Tab) juga membuka; Esc/ketuk di luar menutup.
    <x-tooltip label="SKU" text="Kode unik barang untuk gudang." />
    <x-tooltip label="Foto" text="Tampil di ..." :tips="['Rasio 1:1, min. 1000×1000 px', 'Latar polos']" />
    <x-tooltip label="...">Isi bebas (HTML) lewat slot.</x-tooltip>
    text  = penjelasan fungsi isian. tips = string/array saran agar hasilnya maksimal (tampil sebagai daftar "Tips").
    label = nama isian untuk pembaca layar ("Info: SKU").
    Perilaku: resources/js/components/tooltip.js (panel dipindah ke <body> saat terbuka,
    jadi tidak terpotong card/modal/sidebar dan tidak menggeser layout).
--}}
@props([
    'text' => null,
    'tips' => null,
    'label' => null,
    'icon' => 'question',
])
@php
    $tipId = 'tip-'.\Illuminate\Support\Str::random(8);
    $tips = collect(is_array($tips) ? $tips : [$tips])->filter(fn ($tip) => filled($tip))->values();
    $content = trim((string) $slot);
@endphp
<span {{ $attributes->class('tip') }} data-tip>
    <button type="button" class="tip-trigger" data-tip-trigger aria-describedby="{{ $tipId }}" aria-expanded="false"
            aria-label="{{ $label ? 'Info: '.$label : 'Info' }}">
        <x-icon :name="$icon" class="size-4" />
    </button>
    <span role="tooltip" id="{{ $tipId }}" class="tip-panel" data-tip-panel hidden>
        @if (filled($text))
            <span class="tip-text">{{ $text }}</span>
        @endif
        @if ($content !== '')
            <span class="tip-text">{{ $slot }}</span>
        @endif
        @if ($tips->isNotEmpty())
            <span class="tip-tips">
                <span class="tip-tips-title"><x-icon name="sparkles" class="size-3.5" /> Tips</span>
                @if ($tips->count() === 1)
                    <span class="block">{{ $tips->first() }}</span>
                @else
                    <span class="tip-list">
                        @foreach ($tips as $tip)
                            <span class="tip-item">{{ $tip }}</span>
                        @endforeach
                    </span>
                @endif
            </span>
        @endif
        <span class="tip-arrow" data-tip-arrow></span>
    </span>
</span>
