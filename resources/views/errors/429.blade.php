<x-layouts.base :noindex="true" title="Terlalu banyak percobaan">
    <div class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
        <a href="{{ route('home') }}"><x-wordmark /></a>
        <p class="mt-10 text-7xl font-extrabold tracking-tight text-ink font-display">429</p>
        <h1 class="mt-3 text-2xl font-bold text-stone-900">Terlalu banyak percobaan</h1>
        <p class="mt-2 max-w-md text-stone-500">
            Demi keamanan akun, coba lagi
            @if ($seconds = $exception->getHeaders()['Retry-After'] ?? null)
                dalam {{ $seconds }} detik.
            @else
                beberapa saat lagi.
            @endif
        </p>
        <a href="{{ url()->previous() }}" class="btn btn-ink btn-pill mt-8">Kembali</a>
    </div>
</x-layouts.base>
