<x-layouts.base title="Akses ditolak">
    <div class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
        <a href="{{ route('home') }}"><x-logo /></a>
        <p class="mt-10 text-7xl font-extrabold tracking-tight text-brand-600">403</p>
        <h1 class="mt-3 text-2xl font-bold text-stone-900">Akses ditolak</h1>
        <p class="mt-2 max-w-md text-stone-500">Kamu tidak memiliki izin untuk membuka halaman ini.</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-8">Kembali ke beranda</a>
    </div>
</x-layouts.base>
