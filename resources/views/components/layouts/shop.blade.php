@props(['title' => null])
@php
    $user = auth()->user();
    $nav = [
        ['label' => 'Beranda', 'route' => 'home', 'icon' => 'home', 'active' => request()->routeIs('home')],
        ['label' => 'Produk', 'route' => 'products.index', 'icon' => 'storefront', 'active' => request()->routeIs('products.*')],
    ];
@endphp
<x-layouts.base :title="$title">
    @isset($scripts)
        <x-slot:scripts>{{ $scripts }}</x-slot:scripts>
    @endisset
    {{-- Bar promo --}}
    <div class="bg-stone-900 text-center text-xs font-medium text-stone-200">
        <p class="mx-auto max-w-7xl px-4 py-2">
            <x-icon name="truck" class="mr-1 inline size-4 text-brand-400" />
            Kirim ke seluruh Indonesia · Produk original bergaransi
        </p>
    </div>

    <header x-data="{ mobileSearch: false }" class="sticky top-0 z-40 border-b border-stone-200/70 bg-white/85 backdrop-blur-lg">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="shrink-0"><x-logo /></a>

            <nav class="ml-6 hidden items-center gap-1 md:flex">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                       @class(['rounded-lg px-3 py-2 text-sm font-semibold transition', 'text-brand-700 bg-brand-50' => $item['active'], 'text-stone-600 hover:text-stone-900' => ! $item['active']])>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <form action="{{ route('products.index') }}" method="GET" class="relative ml-auto hidden w-full max-w-sm lg:block">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari casing, charger, earphone..."
                       class="input rounded-full bg-stone-50 pl-10">
            </form>

            <div class="ml-auto flex items-center gap-1 lg:ml-2">
                <button type="button" @click="mobileSearch = !mobileSearch" class="btn btn-ghost p-2.5 lg:hidden" aria-label="Cari">
                    <x-icon name="search" />
                </button>

                <a href="{{ route('cart.index') }}" class="btn btn-ghost relative p-2.5" aria-label="Keranjang">
                    <x-icon name="cart" />
                    @if ($cartCount > 0)
                        <span class="absolute right-0.5 top-0.5 grid min-w-5 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white ring-2 ring-white">
                            {{ $cartCount > 99 ? '99+' : $cartCount }}
                        </span>
                    @endif
                </a>

                @auth
                    <div x-data="{ open: false }" class="relative hidden md:block">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                                class="flex items-center gap-2 rounded-full p-1 pr-3 transition hover:bg-stone-100">
                            <span class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ $user->initials() }}</span>
                            <span class="max-w-28 truncate text-sm font-semibold text-stone-700">{{ \Illuminate\Support\Str::words($user->name, 1, '') }}</span>
                            <x-icon name="chevron-down" class="size-4 text-stone-400" />
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.right
                             class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-stone-200 bg-white p-1.5 shadow-xl shadow-stone-900/10">
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-semibold text-stone-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-stone-500">{{ $user->email }}</p>
                            </div>
                            <div class="my-1 h-px bg-stone-100"></div>
                            @if ($user->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-50"><x-icon name="dashboard" class="size-4" /> Admin Panel</a>
                            @else
                                <a href="{{ route('account.dashboard') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-50"><x-icon name="user" class="size-4" /> Akun Saya</a>
                                <a href="{{ route('account.orders.index') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-50"><x-icon name="receipt" class="size-4" /> Pesanan Saya</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50"><x-icon name="logout" class="size-4" /> Keluar</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost hidden md:inline-flex">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary hidden md:inline-flex">Daftar</a>
                @endauth
            </div>
        </div>

        <div x-show="mobileSearch" x-cloak x-transition class="border-t border-stone-100 px-4 py-3 lg:hidden">
            <form action="{{ route('products.index') }}" method="GET" class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari produk..." class="input rounded-full bg-stone-50 pl-10">
            </form>
        </div>
    </header>

    <main class="pb-24 md:pb-0">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-stone-200/70 bg-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-4 lg:px-8">
            <div class="md:col-span-2">
                <x-logo />
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-stone-500">{{ config('shop.tagline') }}. Belanja aman, pembayaran mudah, pengiriman cepat ke seluruh Indonesia.</p>
            </div>
            <div>
                <h4 class="text-sm font-bold text-stone-900">Belanja</h4>
                <ul class="mt-3 space-y-2 text-sm text-stone-500">
                    <li><a href="{{ route('products.index') }}" class="hover:text-brand-600">Semua Produk</a></li>
                    <li><a href="{{ route('products.index', ['urut' => 'terbaru']) }}" class="hover:text-brand-600">Produk Terbaru</a></li>
                    <li><a href="{{ route('cart.index') }}" class="hover:text-brand-600">Keranjang</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-bold text-stone-900">Bantuan</h4>
                <ul class="mt-3 space-y-2 text-sm text-stone-500">
                    <li><a href="{{ auth()->check() ? route('account.orders.index') : route('login') }}" class="hover:text-brand-600">Lacak Pesanan</a></li>
                    @if (config('shop.whatsapp'))
                        <li><a href="https://wa.me/{{ config('shop.whatsapp') }}" target="_blank" rel="noopener" class="hover:text-brand-600">Chat WhatsApp</a></li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="border-t border-stone-100 py-5 text-center text-xs text-stone-400">
            &copy; {{ date('Y') }} {{ config('shop.full_name') }}. Semua hak dilindungi.
        </div>
    </footer>

    {{-- Navigasi bawah untuk mobile --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden">
        <div class="grid grid-cols-4">
            @php
                $mobileNav = [
                    ['Beranda', route('home'), 'home', request()->routeIs('home')],
                    ['Produk', route('products.index'), 'storefront', request()->routeIs('products.*')],
                    ['Keranjang', route('cart.index'), 'cart', request()->routeIs('cart.*', 'checkout.*')],
                    ['Akun', $user ? $user->homeRoute() : route('login'), 'user', request()->routeIs('account.*', 'login', 'register')],
                ];
            @endphp
            @foreach ($mobileNav as [$label, $url, $icon, $active])
                <a href="{{ $url }}" @class(['relative flex flex-col items-center gap-0.5 py-2.5 text-[11px] font-semibold', 'text-brand-600' => $active, 'text-stone-500' => ! $active])>
                    <x-icon :name="$icon" class="size-6" />
                    {{ $label }}
                    @if ($icon === 'cart' && $cartCount > 0)
                        <span class="absolute left-1/2 top-1.5 ml-2 grid min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[9px] font-bold text-white">{{ $cartCount }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>
</x-layouts.base>
