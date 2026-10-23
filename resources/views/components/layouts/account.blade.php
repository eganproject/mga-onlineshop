@props(['title' => null, 'heading' => null])
@php
    $links = [
        ['Ringkasan', 'account.dashboard', 'dashboard', 'account.dashboard'],
        ['Pesanan saya', 'account.orders.index', 'receipt', 'account.orders.*'],
        ['Wishlist', 'account.wishlist', 'heart', 'account.wishlist'],
        ['Ulasan', 'account.reviews.index', 'star', 'account.reviews.*'],
        ['Alamat', 'account.addresses.index', 'map-pin', 'account.addresses.*'],
        ['Profil & keamanan', 'account.profile.edit', 'user', 'account.profile.*'],
    ];
@endphp
<x-layouts.shop :title="$title">
    @isset($scripts)
        <x-slot:scripts>{{ $scripts }}</x-slot:scripts>
    @endisset
    <section class="border-b border-stone-200 bg-sand-50">
        <div class="shop-container pt-6 sm:pt-12">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow text-stone-500">Akun saya</p>
                    <h1 class="display mt-2 text-4xl leading-none sm:text-5xl">Halo, {{ \Illuminate\Support\Str::words(auth()->user()->name, 1, '') }}</h1>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="link-underline -mb-3 py-3 text-sm font-semibold text-stone-500 hover:text-rose-600 lg:mb-0 lg:py-0">Keluar</button>
                </form>
            </div>
            <nav class="no-scrollbar -mx-4 mt-4 flex gap-6 overflow-x-auto px-4 sm:mx-0 sm:mt-6 sm:px-0" aria-label="Menu akun">
                @foreach ($links as [$label, $route, $icon, $pattern])
                    @php($active = request()->routeIs($pattern))
                    <a href="{{ route($route) }}" @class(['flex min-h-11 shrink-0 items-center gap-2 border-b-2 pb-3 pt-1 text-sm font-semibold transition lg:min-h-0 lg:pt-0', 'border-ink text-ink' => $active, 'border-transparent text-stone-500 hover:text-ink' => ! $active])>
                        <x-icon :name="$icon" class="size-4" /> {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
    </section>
    <div class="shop-container py-6 sm:py-8 lg:py-10">
        @if (! auth()->user()->hasVerifiedEmail())
            <div class="mb-6 flex flex-col gap-3 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-start gap-2.5">
                    <x-icon name="envelope" class="mt-0.5 size-5 shrink-0" />
                    <span>Email <strong>{{ auth()->user()->email }}</strong> belum diverifikasi. Verifikasi supaya info pesanan & promo selalu sampai.</span>
                </p>
                <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                    @csrf
                    <button class="btn btn-ink btn-pill btn-sm">Kirim ulang tautan</button>
                </form>
            </div>
        @endif
        <section class="min-w-0">
            @if ($heading)
                <h2 class="mb-6 font-display text-2xl font-extrabold tracking-tight uppercase">{{ $heading }}</h2>
            @endif
            {{ $slot }}
        </section>
    </div>
</x-layouts.shop>
