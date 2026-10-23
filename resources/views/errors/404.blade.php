<x-layouts.base title="Halaman tidak ditemukan">
    <div class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
        <a href="{{ route('home') }}"><x-wordmark /></a>
        <p class="mt-10 text-7xl font-extrabold tracking-tight text-ink font-display">404</p>
        <h1 class="mt-3 text-2xl font-bold text-stone-900">Halaman tidak ditemukan</h1>
        <p class="mt-2 max-w-md text-stone-500">Halaman yang kamu cari mungkin sudah dipindah atau tidak tersedia.</p>
        <a href="{{ url('/') }}" class="btn btn-ink btn-pill mt-8">Kembali ke beranda</a>
    </div>
</x-layouts.base>
