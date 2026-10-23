{{--
    Navigasi bawah toko (mobile & tablet, < lg). Desktop memakai menu di header.
    Tab "Kategori" membuka panel "Jelajahi" (state `menu` milik x-data layout shop).
--}}
@props(['cartCount' => 0, 'wishlistCount' => 0])
@php
    $user = auth()->user();
    $isAdmin = (bool) $user?->isAdmin();
    $catalogActive = request()->routeIs('products.*', 'articles.*');
    $cartActive = request()->routeIs('cart.*', 'checkout.*');

    $left = [
        ['label' => 'Beranda', 'icon' => 'home', 'url' => route('home'), 'active' => request()->routeIs('home')],
    ];
    $right = [
        $isAdmin
            ? ['label' => 'Promo', 'icon' => 'tag', 'url' => route('products.index', ['promo' => 1]), 'active' => request()->boolean('promo')]
            : ['label' => 'Wishlist', 'icon' => 'heart', 'url' => $user ? route('account.wishlist') : route('login'), 'active' => request()->routeIs('account.wishlist'), 'wishlist' => true],
        [
            'label' => $isAdmin ? 'Admin' : ($user ? 'Akun' : 'Masuk'),
            'icon' => $isAdmin ? 'dashboard' : 'user',
            'url' => $user ? $user->homeRoute() : route('login'),
            'active' => request()->routeIs('account.*') && ! request()->routeIs('account.wishlist'),
        ],
    ];
@endphp
<nav class="bottom-nav fixed inset-x-0 bottom-0 z-40 border-t border-stone-200/80 bg-white/95 shadow-[0_-6px_24px_-12px_rgb(15_23_42/0.18)] backdrop-blur lg:hidden"
     aria-label="Navigasi bawah">
    <ul class="mx-auto grid h-16 max-w-xl grid-cols-5 items-stretch px-1">
        @foreach ($left as $item)
            <li>
                <a href="{{ $item['url'] }}" @class(['bottom-nav-item', 'is-active' => $item['active']]) @if ($item['active']) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" class="size-6" />
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach

        <li>
            <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-controls="explore-sheet"
                    class="bottom-nav-item {{ $catalogActive ? 'is-active' : '' }}" x-bind:class="menu && 'is-active'">
                <x-icon name="dashboard" class="size-6" />
                <span>Kategori</span>
            </button>
        </li>

        {{-- Tombol tengah: Keranjang --}}
        <li class="relative flex justify-center">
            <a href="{{ route('cart.index') }}" class="group -mt-5 flex flex-col items-center gap-1 focus-visible:outline-none"
               aria-label="Keranjang ({{ $cartCount }} barang)" @if ($cartActive) aria-current="page" @endif>
                <span @class([
                    'relative grid size-14 place-items-center rounded-full text-white shadow-lg ring-4 ring-white transition group-active:scale-95 group-focus-visible:ring-brand-200',
                    'bg-brand-700 shadow-brand-700/40' => $cartActive,
                    'bg-brand-600 shadow-brand-600/30' => ! $cartActive,
                ])>
                    <x-icon name="bag" class="size-6" />
                    @if ($cartCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 grid min-w-5 place-items-center rounded-full bg-accent-500 px-1 text-[11px] leading-5 font-bold text-white ring-2 ring-white">
                            {{ $cartCount > 99 ? '99+' : $cartCount }}
                        </span>
                    @endif
                </span>
                <span @class(['text-[11px] leading-none', 'font-semibold text-brand-700' => $cartActive, 'font-medium text-stone-500' => ! $cartActive])>Keranjang</span>
            </a>
        </li>

        @foreach ($right as $item)
            <li>
                <a href="{{ $item['url'] }}" @class(['bottom-nav-item', 'is-active' => $item['active']]) @if ($item['active']) aria-current="page" @endif
                   @if (! empty($item['wishlist'])) x-data="{ count: {{ $wishlistCount }} }" @wishlist-updated.window="count = $event.detail.count" @endif>
                    <span class="relative">
                        <x-icon :name="$item['icon']" class="size-6" />
                        @if (! empty($item['wishlist']))
                            <span x-show="count > 0" x-text="count > 99 ? '99+' : count" @if (! $wishlistCount) x-cloak @endif
                                  class="absolute -right-2.5 -top-1.5 grid min-w-[18px] place-items-center rounded-full bg-brand-600 px-1 text-[10px] leading-[18px] font-bold text-white ring-2 ring-white">{{ $wishlistCount }}</span>
                        @endif
                    </span>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
