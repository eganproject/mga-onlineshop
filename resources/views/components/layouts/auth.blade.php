@props(['title' => null, 'heading', 'subheading' => null])
<x-layouts.base :title="$title" body-class="bg-white text-stone-800">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Panel brand --}}
        <div class="relative hidden overflow-hidden bg-gradient-to-br from-brand-600 via-brand-700 to-stone-900 lg:block">
            <div class="absolute -left-20 -top-20 size-80 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-24 right-0 size-96 rounded-full bg-amber-300/20 blur-3xl"></div>
            <div class="relative flex h-full flex-col justify-between p-12 text-white">
                <a href="{{ route('home') }}"><x-logo dark /></a>
                <div class="max-w-md">
                    <h2 class="text-4xl font-extrabold leading-tight tracking-tight">Aksesoris gadget favoritmu, semua ada di satu gudang.</h2>
                    <p class="mt-4 text-base text-white/75">Casing, charger, kabel data, earphone, dan banyak lagi. Original, bergaransi, dan harga bersahabat.</p>
                    <div class="mt-8 grid grid-cols-3 gap-3 text-sm">
                        @foreach ([['shield', 'Original'], ['truck', 'Kirim cepat'], ['lock', 'Bayar aman']] as [$icon, $label])
                            <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
                                <x-icon :name="$icon" class="size-6 text-amber-200" />
                                <p class="mt-2 font-semibold">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="text-xs text-white/50">&copy; {{ date('Y') }} {{ config('shop.full_name') }}</p>
            </div>
        </div>

        {{-- Form --}}
        <div class="flex flex-col px-5 py-8 sm:px-10">
            <div class="flex items-center justify-between lg:justify-end">
                <a href="{{ route('home') }}" class="lg:hidden"><x-logo /></a>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-500 hover:text-stone-800">
                    <x-icon name="arrow-left" class="size-4" /> Kembali ke toko
                </a>
            </div>
            <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
                <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">{{ $heading }}</h1>
                @if ($subheading)
                    <p class="mt-2 text-sm text-stone-500">{{ $subheading }}</p>
                @endif
                <div class="mt-8">{{ $slot }}</div>
            </div>
        </div>
    </div>
</x-layouts.base>
