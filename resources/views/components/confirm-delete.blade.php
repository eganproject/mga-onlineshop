{{-- Tombol hapus dengan modal konfirmasi (lihat resources/js/components/dialogs.js).
     `compact` = di kolom Aksi tabel: teks disembunyikan di laptop (lg–xl, ikon + title) supaya tabel muat tanpa digeser. --}}
@props(['action', 'message' => 'Yakin ingin menghapus data ini?', 'label' => 'Hapus', 'title' => null, 'ok' => 'Ya, hapus', 'compact' => false])
@php
    // Teks atribut komponen sudah di-escape sekali oleh Blade; buka dulu supaya tidak tampil "&amp;".
    $plain = fn (?string $text) => html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5);
@endphp
<form method="POST" action="{{ $action }}" data-confirm="{{ $plain($message) }}" data-confirm-title="{{ $plain($title ?? $label.'?') }}" data-confirm-ok="{{ $plain($ok) }}" {{ $attributes }}>
    @csrf
    @method('DELETE')
    @if ($slot->isEmpty())
        <button class="btn btn-ghost btn-sm text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="{{ $label }}">
            <x-icon name="trash" class="size-4" /> <span @class(['sr-only sm:not-sr-only', 'lg:sr-only 2xl:not-sr-only' => $compact])>{{ $label }}</span>
        </button>
    @else
        {{ $slot }}
    @endif
</form>
