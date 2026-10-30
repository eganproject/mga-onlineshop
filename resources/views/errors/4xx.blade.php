@php($status = isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400)
<x-layouts.error :code="$status" title="Permintaan tidak dapat diproses" icon="info"
                 message="Ada yang kurang tepat dengan permintaan ini. Coba kembali ke halaman sebelumnya atau mulai lagi dari beranda.">
    <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
        <a href="{{ url('/') }}" class="btn btn-ink btn-pill">
            <x-icon name="home" class="size-4" /> Ke beranda
        </a>
        <a href="{{ url('/produk') }}" class="btn btn-outline btn-pill">
            <x-icon name="bag" class="size-4" /> Lihat produk
        </a>
    </div>
</x-layouts.error>
