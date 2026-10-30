{{--
    Layout halaman error (404, 403, 419, 429, 500, 503).
    Sengaja berdiri sendiri: tidak memakai database, sesi, atau layout toko, supaya
    tetap tampil walau server sedang bermasalah (500) atau dalam perawatan (503).
    Data tambahan (mis. kategori di 404) diambil di view masing-masing dengan try/catch.
--}}
@props([
    'code',
    'title',
    'message' => null,
    'icon' => 'question',
    'tone' => 'brand', // brand | accent
])
@php
    $shopName = config('shop.full_name', config('app.name'));
    $whatsapp = preg_replace('/\D/', '', (string) config('shop.whatsapp'));
    $year = now()->year;

    // CSS hasil build (tanpa JS: halaman error cukup HTML biasa). Kalau manifest Vite tidak ada, halaman tetap tampil tanpa gaya.
    try {
        $assets = app(\Illuminate\Foundation\Vite::class)('resources/css/app.css')->toHtml();
    } catch (\Throwable) {
        $assets = '';
    }
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#21939f">
    <title>{{ $title }} · {{ $shopName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {!! $assets !!}
</head>
<body class="min-h-full bg-cream font-sans text-ink antialiased">
    <div class="relative flex min-h-screen flex-col overflow-hidden">
        {{-- Hiasan latar lembut warna brand --}}
        <div aria-hidden="true" class="pointer-events-none absolute -top-40 -right-32 size-[28rem] rounded-full bg-brand-200/40 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-48 -left-40 size-[30rem] rounded-full bg-accent-200/40 blur-3xl"></div>

        <header class="relative shop-container flex h-16 items-center justify-between sm:h-20">
            <a href="{{ url('/') }}" aria-label="Kembali ke beranda {{ $shopName }}">
                <x-logo class="h-9 sm:h-11" />
            </a>
            <a href="{{ url('/produk') }}" class="hidden text-sm font-semibold text-ink/70 transition hover:text-brand-700 sm:inline-flex sm:items-center sm:gap-1.5">
                Belanja sekarang <x-icon name="arrow-right" class="size-4" />
            </a>
        </header>

        <main class="relative flex flex-1 items-center">
            <div class="shop-container py-10 sm:py-16">
                <div class="mx-auto max-w-2xl text-center">
                    <div @class([
                        'mx-auto grid size-16 place-items-center rounded-2xl shadow-sm sm:size-20',
                        'bg-brand-50 text-brand-600 ring-1 ring-brand-100' => $tone === 'brand',
                        'bg-accent-50 text-accent-600 ring-1 ring-accent-100' => $tone === 'accent',
                    ])>
                        <x-icon :name="$icon" class="size-8 sm:size-10" />
                    </div>

                    <p class="mt-6 font-display text-6xl font-extrabold tracking-tight sm:text-8xl">
                        <span class="bg-gradient-to-r from-brand-600 to-brand-400 bg-clip-text text-transparent">{{ substr((string) $code, 0, 1) }}</span><span class="text-accent-400">{{ substr((string) $code, 1, 1) }}</span><span class="bg-gradient-to-r from-brand-400 to-brand-600 bg-clip-text text-transparent">{{ substr((string) $code, 2) }}</span>
                    </p>

                    <h1 class="mt-3 text-2xl font-bold text-ink sm:text-3xl">{{ $title }}</h1>
                    @if ($message)
                        <p class="mx-auto mt-3 max-w-lg text-base leading-relaxed text-stone-600">{{ $message }}</p>
                    @endif

                    {{ $slot }}
                </div>
            </div>
        </main>

        <footer class="relative border-t border-ink/5 bg-white/60 backdrop-blur">
            <div class="shop-container flex flex-col items-center justify-between gap-2 py-5 text-xs text-stone-500 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:flex-row">
                <p>&copy; {{ $year }} {{ $shopName }}</p>
                @if ($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-medium text-stone-600 transition hover:text-brand-700">
                        <x-icon name="chat" class="size-4" /> Butuh bantuan? Chat admin via WhatsApp
                    </a>
                @endif
            </div>
        </footer>
    </div>
</body>
</html>
