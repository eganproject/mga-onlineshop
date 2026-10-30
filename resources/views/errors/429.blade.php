@php
    $seconds = isset($exception) && method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? null) : null;
@endphp
<x-layouts.error code="429" title="Terlalu banyak percobaan" icon="shield" tone="accent"
                 :message="'Demi keamanan, permintaan dari perangkatmu kami jeda sebentar. Coba lagi '.($seconds ? 'dalam '.$seconds.' detik.' : 'beberapa saat lagi.')">
    <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
        <a href="{{ url()->previous(url('/')) }}" class="btn btn-ink btn-pill">
            <x-icon name="arrow-left" class="size-4" /> Kembali
        </a>
        <a href="{{ url('/') }}" class="btn btn-outline btn-pill">
            <x-icon name="home" class="size-4" /> Ke beranda
        </a>
    </div>
</x-layouts.error>
