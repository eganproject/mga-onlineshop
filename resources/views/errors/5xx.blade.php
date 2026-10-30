<x-layouts.error :code="isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500" title="Ada kendala di server kami" icon="warning" tone="accent"
                 message="Maaf, terjadi kesalahan saat memuat halaman ini. Tim kami sudah mendapat catatannya. Coba muat ulang beberapa saat lagi.">
    <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
        <a href="{{ url()->current() }}" class="btn btn-ink btn-pill">
            <x-icon name="refresh" class="size-4" /> Coba lagi
        </a>
        <a href="{{ url('/') }}" class="btn btn-outline btn-pill">
            <x-icon name="home" class="size-4" /> Ke beranda
        </a>
    </div>
</x-layouts.error>
