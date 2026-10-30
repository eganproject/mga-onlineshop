<x-layouts.error code="419" title="Sesi sudah kedaluwarsa" icon="clock" tone="accent"
                 message="Halaman terlalu lama dibuka sehingga sesi keamanannya habis. Muat ulang halaman sebelumnya lalu kirim lagi formulirnya.">
    <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
        <a href="{{ url()->previous(url('/')) }}" class="btn btn-ink btn-pill">
            <x-icon name="refresh" class="size-4" /> Kembali &amp; muat ulang
        </a>
        <a href="{{ url('/') }}" class="btn btn-outline btn-pill">
            <x-icon name="home" class="size-4" /> Ke beranda
        </a>
    </div>
</x-layouts.error>
