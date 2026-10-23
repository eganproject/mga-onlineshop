@props(['title' => null, 'heading', 'subheading' => null])
<x-layouts.base :title="$title" body-class="bg-white text-stone-800">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Panel brand --}}
        <div class="relative hidden overflow-hidden bg-ink lg:block">
            <span class="pointer-events-none absolute -bottom-10 -left-4 select-none font-display text-[22rem] leading-none font-black tracking-[-0.06em] text-white/[0.05] uppercase" aria-hidden="true">{{ config('shop.name') }}</span>
            <div class="relative flex h-full flex-col justify-between p-12 text-white">
                <a href="{{ route('home') }}" class="self-start"><x-wordmark dark /></a>
                <div class="max-w-md">
                    <p class="eyebrow text-brand-400">{{ config('shop.full_name') }}</p>
                    <h2 class="display mt-4 text-5xl leading-[0.92]">Aksesoris gadget favoritmu, semua di satu gudang.</h2>
                    <p class="mt-5 text-base text-white/60">Casing, charger, kabel data, earphone, dan banyak lagi. Original, bergaransi, dan harga bersahabat.</p>
                    <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/80">
                        @foreach ([['shield', 'Original'], ['truck', 'Kirim cepat'], ['lock', 'Bayar aman']] as [$icon, $label])
                            <li class="flex items-center gap-2"><x-icon :name="$icon" class="size-4 text-white/50" /> {{ $label }}</li>
                        @endforeach
                    </ul>
                </div>
                <p class="text-xs text-white/40">&copy; {{ date('Y') }} {{ config('shop.full_name') }}</p>
            </div>
        </div>

        {{-- Form --}}
        <div class="flex flex-col px-5 py-8 sm:px-10">
            <div class="flex items-center justify-between lg:justify-end">
                <a href="{{ route('home') }}" class="lg:hidden"><x-wordmark /></a>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-500 hover:text-stone-800">
                    <x-icon name="arrow-left" class="size-4" /> Kembali ke toko
                </a>
            </div>
            <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
                <h1 class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $heading }}</h1>
                @if ($subheading)
                    <p class="mt-2 text-sm text-stone-500">{{ $subheading }}</p>
                @endif
                <div class="mt-8">{{ $slot }}</div>
            </div>
        </div>
    </div>
</x-layouts.base>
