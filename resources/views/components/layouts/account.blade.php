@props(['title' => null, 'heading' => null])
@php
    $links = [
        ['Ringkasan', 'account.dashboard', 'dashboard', 'account.dashboard'],
        ['Pesanan saya', 'account.orders.index', 'receipt', 'account.orders.*'],
        ['Alamat', 'account.addresses.index', 'map-pin', 'account.addresses.*'],
        ['Profil & keamanan', 'account.profile.edit', 'user', 'account.profile.*'],
    ];
@endphp
<x-layouts.shop :title="$title">
    @isset($scripts)
        <x-slot:scripts>{{ $scripts }}</x-slot:scripts>
    @endisset
    <section class="border-b border-stone-200 bg-sand-50">
        <div class="shop-container pt-8 sm:pt-12">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow text-stone-500">Akun saya</p>
                    <h1 class="display mt-2 text-4xl leading-none sm:text-5xl">Halo, {{ \Illuminate\Support\Str::words(auth()->user()->name, 1, '') }}</h1>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="link-underline text-sm font-semibold text-stone-500 hover:text-rose-600">Keluar</button>
                </form>
            </div>
            <nav class="no-scrollbar -mx-4 mt-6 flex gap-6 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Menu akun">
                @foreach ($links as [$label, $route, $icon, $pattern])
                    @php($active = request()->routeIs($pattern))
                    <a href="{{ route($route) }}" @class(['flex shrink-0 items-center gap-2 border-b-2 pb-3 text-sm font-semibold transition', 'border-ink text-ink' => $active, 'border-transparent text-stone-500 hover:text-ink' => ! $active])>
                        <x-icon :name="$icon" class="size-4" /> {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
    </section>
    <div class="shop-container py-8 lg:py-10">
        <section class="min-w-0">
            @if ($heading)
                <h2 class="mb-6 font-display text-2xl font-extrabold tracking-tight uppercase">{{ $heading }}</h2>
            @endif
            {{ $slot }}
        </section>
    </div>
</x-layouts.shop>
