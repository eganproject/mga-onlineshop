@props(['title' => null, 'description' => null, 'image' => null, 'canonical' => null, 'type' => 'website', 'noindex' => false])
@php
    $user = auth()->user();
    $announcements = \App\Support\Storefront::announcements();
    $nav = [
        ['label' => 'Semua Produk', 'url' => route('products.index'), 'active' => request()->routeIs('products.index') && ! request()->hasAny(['kategori', 'promo', 'urut'])],
        ['label' => 'Terbaru', 'url' => route('products.index', ['urut' => 'terbaru']), 'active' => request('urut') === 'terbaru'],
    ];
    $promoNav = ['label' => 'Promo', 'url' => route('products.index', ['promo' => 1]), 'active' => request()->boolean('promo')];
    $accountUrl = $user ? $user->homeRoute() : route('login');
@endphp
<x-layouts.base :title="$title" :description="$description" :image="$image" :canonical="$canonical" :type="$type" :noindex="$noindex" body-class="bg-white text-ink">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset
    @isset($scripts)
        <x-slot:scripts>{{ $scripts }}</x-slot:scripts>
    @endisset

    <div x-data="{ menu: false, search: false, cats: false }"
         x-effect="document.documentElement.classList.toggle('overflow-hidden', menu || search)"
         @keydown.escape.window="menu = false; search = false"
         class="flex min-h-screen flex-col">

        {{-- Topbar: pengumuman bergantian (diatur di Admin › Tampilan Beranda) --}}
        <div class="bg-brand-950 text-white">
            <div class="shop-container grid h-9 grid-cols-[auto_1fr_auto] items-center gap-3 text-[11px] sm:text-xs">
                <div class="hidden items-center gap-5 text-white/70 lg:flex">
                    <a href="{{ auth()->check() && ! $user->isAdmin() ? route('account.orders.index') : route('login') }}" class="flex items-center gap-1.5 transition hover:text-white">
                        <x-icon name="truck" class="size-3.5" /> Lacak pesanan
                    </a>
                    @if (config('shop.whatsapp'))
                        <a href="https://wa.me/{{ config('shop.whatsapp') }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 transition hover:text-white">
                            <x-icon name="chat" class="size-3.5" /> Bantuan
                        </a>
                    @endif
                </div>

                @if (count($announcements))
                    <div class="relative col-start-2 flex h-full min-w-0 items-center justify-center"
                         x-data="{ i: 0, n: {{ count($announcements) }}, t: null,
                                   go(d) { this.i = (this.i + d + this.n) % this.n },
                                   play() { if (this.n > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) this.t = setInterval(() => this.go(1), 4500) },
                                   stop() { clearInterval(this.t) } }"
                         x-init="play()" @mouseenter="stop()" @mouseleave="play()" aria-live="polite">
                        @if (count($announcements) > 1)
                            <button type="button" @click="go(-1)" class="absolute left-0 grid size-7 place-items-center text-white/60 hover:text-white lg:static lg:mr-3" aria-label="Pengumuman sebelumnya">
                                <x-icon name="chevron-left" class="size-3.5" />
                            </button>
                        @endif
                        <div class="relative h-full min-w-0 flex-1 overflow-hidden lg:max-w-md lg:flex-none lg:w-[28rem]">
                            @foreach ($announcements as $text)
                                <p x-show="i === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif
                                   x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                                   x-transition:leave="transition duration-300 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="-translate-y-full opacity-0"
                                   class="absolute inset-0 flex items-center justify-center truncate px-7 text-center font-medium tracking-wide lg:px-0">
                                    <span class="truncate">{{ $text }}</span>
                                </p>
                            @endforeach
                        </div>
                        @if (count($announcements) > 1)
                            <button type="button" @click="go(1)" class="absolute right-0 grid size-7 place-items-center text-white/60 hover:text-white lg:static lg:ml-3" aria-label="Pengumuman berikutnya">
                                <x-icon name="chevron-right" class="size-3.5" />
                            </button>
                        @endif
                    </div>
                @endif

                <div class="col-start-3 hidden items-center gap-5 text-white/70 lg:flex">
                    @if (config('shop.instagram'))
                        <a href="https://instagram.com/{{ ltrim(config('shop.instagram'), '@') }}" target="_blank" rel="noopener" class="transition hover:text-white">Instagram</a>
                    @endif
                    @guest
                        <a href="{{ route('login') }}" class="transition hover:text-white">Masuk</a>
                        <a href="{{ route('register') }}" class="font-semibold text-accent-300 transition hover:text-accent-200">Daftar &amp; dapat promo</a>
                    @else
                        <a href="{{ $accountUrl }}" class="transition hover:text-white">Hai, {{ \Illuminate\Support\Str::words($user->name, 1, '') }}</a>
                    @endguest
                </div>
            </div>
        </div>

        {{-- Header --}}
        <header class="sticky top-0 z-40 border-b border-stone-200 bg-white/95 backdrop-blur">
            <div class="shop-container flex h-16 items-center gap-2 lg:h-[76px] lg:gap-8">
                {{-- Mobile: menu --}}
                <button type="button" @click="menu = true" class="-ml-2 grid size-10 shrink-0 place-items-center rounded-full hover:bg-sand-100 lg:hidden" aria-label="Buka menu">
                    <x-icon name="menu" />
                </button>

                {{-- Logo --}}
                <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ config('shop.full_name') }}">
                    <x-logo class="h-9 lg:h-11" />
                </a>

                {{-- Navigasi utama (desktop, di tengah) --}}
                <nav class="hidden flex-1 items-center justify-center gap-8 lg:flex" aria-label="Navigasi utama">
                    @foreach ($nav as $item)
                        <a href="{{ $item['url'] }}" @class([
                            'relative py-7 text-sm font-medium transition hover:text-brand-600',
                            'text-ink after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-brand-500' => $item['active'],
                        ])>{{ $item['label'] }}</a>
                    @endforeach
                    @if ($navCategories->isNotEmpty())
                        <div class="relative" @mouseenter="cats = true" @mouseleave="cats = false">
                            <button type="button" @click="cats = !cats" :aria-expanded="cats"
                                    @class(['flex items-center gap-1 py-7 text-sm font-medium transition hover:text-brand-600', 'relative after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-brand-500' => request()->filled('kategori')])>
                                Kategori <x-icon name="chevron-down" class="size-3.5 transition" x-bind:class="cats && 'rotate-180'" />
                            </button>
                            <div x-show="cats" x-cloak x-transition.opacity
                                 class="absolute left-1/2 top-full w-[min(560px,80vw)] -translate-x-1/2 rounded-b-xl border border-stone-200 bg-white p-6 shadow-2xl shadow-stone-900/10">
                                <p class="eyebrow text-stone-400">Belanja per kategori</p>
                                <ul class="mt-4 grid grid-cols-2 gap-x-8 gap-y-1">
                                    @foreach ($navCategories as $category)
                                        <li>
                                            <a href="{{ route('products.index', ['kategori' => $category->slug]) }}" class="group flex items-center justify-between py-2 text-sm font-medium">
                                                <span class="group-hover:text-brand-600">{{ $category->name }}</span>
                                                <x-icon name="arrow-right" class="size-4 -translate-x-1 text-brand-600 opacity-0 transition group-hover:translate-x-0 group-hover:opacity-100" />
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                    <a href="{{ $promoNav['url'] }}" @class([
                        'relative flex items-center gap-1.5 py-7 text-sm font-semibold text-accent-600 transition hover:text-accent-700',
                        'after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-accent-500' => $promoNav['active'],
                    ])><span class="size-1.5 rounded-full bg-accent-500"></span>{{ $promoNav['label'] }}</a>
                </nav>

                {{-- Kanan --}}
                <div class="ml-auto flex items-center gap-0.5 lg:ml-0 lg:gap-1">
                    <form action="{{ route('products.index') }}" method="GET" role="search" class="mr-2 hidden xl:block">
                        <label class="relative block">
                            <span class="sr-only">Cari produk</span>
                            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari produk..."
                                   class="h-10 w-56 rounded-full border-0 bg-stone-100 pl-10 pr-4 text-sm placeholder:text-stone-400 focus:bg-white focus:ring-2 focus:ring-brand-500/40">
                        </label>
                    </form>
                    <button type="button" @click="search = true" class="grid size-10 place-items-center rounded-full hover:bg-sand-100 xl:hidden" aria-label="Cari">
                        <x-icon name="search" />
                    </button>

                    @auth
                        <div x-data="{ open: false }" class="relative hidden lg:block">
                            <button type="button" @click="open = !open" @click.outside="open = false" :aria-expanded="open" aria-label="Akun"
                                    class="grid size-10 place-items-center rounded-full hover:bg-sand-100">
                                <x-icon name="user" />
                            </button>
                            <div x-show="open" x-cloak x-transition.origin.top.right
                                 class="absolute right-0 mt-2 w-60 rounded-xl border border-stone-200 bg-white p-2 shadow-2xl shadow-stone-900/10">
                                <div class="px-3 py-2">
                                    <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                                    <p class="truncate text-xs text-stone-500">{{ $user->email }}</p>
                                </div>
                                <div class="my-1 h-px bg-stone-100"></div>
                                @if ($user->isAdmin())
                                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-sand-100"><x-icon name="dashboard" class="size-4" /> Admin Panel</a>
                                @else
                                    <a href="{{ route('account.dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-sand-100"><x-icon name="user" class="size-4" /> Akun Saya</a>
                                    <a href="{{ route('account.orders.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-sand-100"><x-icon name="receipt" class="size-4" /> Pesanan Saya</a>
                                    <a href="{{ route('account.wishlist') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-sand-100"><x-icon name="heart" class="size-4" /> Wishlist</a>
                                    <a href="{{ route('account.reviews.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-sand-100"><x-icon name="star" class="size-4" /> Ulasan Saya</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-rose-600 hover:bg-rose-50"><x-icon name="logout" class="size-4" /> Keluar</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="hidden size-10 place-items-center rounded-full hover:bg-sand-100 lg:grid" aria-label="Masuk">
                            <x-icon name="user" />
                        </a>
                    @endauth

                    <a href="{{ $accountUrl }}" class="hidden size-10 place-items-center rounded-full hover:bg-sand-100 sm:grid lg:hidden" aria-label="Akun">
                        <x-icon name="user" />
                    </a>

                    @unless ($user?->isAdmin())
                        <a href="{{ $user ? route('account.wishlist') : route('login') }}" class="relative grid size-10 place-items-center rounded-full hover:bg-sand-100"
                           x-data="{ count: {{ $wishlistCount }} }" @wishlist-updated.window="count = $event.detail.count" aria-label="Wishlist">
                            <x-icon name="heart" />
                            <span x-show="count > 0" x-text="count > 99 ? '99+' : count" @if (! $wishlistCount) x-cloak @endif
                                  class="absolute right-0.5 top-0.5 grid min-w-[18px] place-items-center rounded-full bg-brand-600 px-1 text-[10px] leading-[18px] font-bold text-white">{{ $wishlistCount }}</span>
                        </a>
                    @endunless

                    <a href="{{ route('cart.index') }}" class="relative -mr-2 grid size-10 place-items-center rounded-full hover:bg-sand-100 lg:mr-0" aria-label="Keranjang ({{ $cartCount }})">
                        <x-icon name="bag" />
                        @if ($cartCount > 0)
                            <span class="absolute right-0.5 top-0.5 grid min-w-[18px] place-items-center rounded-full bg-accent-500 px-1 text-[10px] leading-[18px] font-bold text-white">
                                {{ $cartCount > 99 ? '99+' : $cartCount }}
                            </span>
                        @endif
                    </a>
                </div>
            </div>
        </header>

        {{-- Panel pencarian --}}
        <div x-show="search" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Cari produk">
            <div x-show="search" x-transition.opacity @click="search = false" class="absolute inset-0 bg-ink/40"></div>
            <div x-show="search"
                 x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="-translate-y-full"
                 class="relative bg-white">
                <div class="shop-container py-6 sm:py-10">
                    <div class="flex items-center justify-between">
                        <p class="eyebrow text-stone-500">Cari produk</p>
                        <button type="button" @click="search = false" class="-mr-2 grid size-10 place-items-center rounded-full hover:bg-sand-100" aria-label="Tutup pencarian">
                            <x-icon name="x" />
                        </button>
                    </div>
                    <form action="{{ route('products.index') }}" method="GET" class="mt-3 flex items-center gap-3 border-b-2 border-ink pb-3">
                        <x-icon name="search" class="size-6 shrink-0 text-stone-400" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Casing, charger, earphone..."
                               x-effect="if (search) $nextTick(() => $el.focus())"
                               class="w-full border-0 bg-transparent p-0 font-display text-2xl font-bold tracking-tight placeholder:text-stone-300 focus:ring-0 sm:text-4xl">
                        <button class="btn btn-ink btn-pill hidden shrink-0 sm:inline-flex">Cari</button>
                    </form>
                    @if ($navCategories->isNotEmpty())
                        <p class="eyebrow mt-6 text-stone-400">Kategori populer</p>
                        <div class="no-scrollbar -mx-4 mt-3 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
                            @foreach ($navCategories->take(8) as $category)
                                <a href="{{ route('products.index', ['kategori' => $category->slug]) }}" class="chip">{{ $category->name }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Menu mobile --}}
        <div x-show="menu" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
            <div x-show="menu" x-transition.opacity @click="menu = false" class="absolute inset-0 bg-ink/40"></div>
            <div x-show="menu"
                 x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                 class="relative flex h-full w-[86%] max-w-sm flex-col overflow-y-auto bg-white">
                <div class="flex h-16 items-center justify-between border-b border-stone-200 px-4">
                    <x-wordmark />
                    <button type="button" @click="menu = false" class="-mr-2 grid size-10 place-items-center rounded-full hover:bg-sand-100" aria-label="Tutup menu">
                        <x-icon name="x" />
                    </button>
                </div>
                <nav class="flex-1 px-4 py-4">
                    <ul class="divide-y divide-stone-100">
                        @foreach ([...$nav, $promoNav] as $item)
                            <li>
                                <a href="{{ $item['url'] }}" class="flex items-center justify-between py-4 font-display text-xl font-extrabold tracking-tight uppercase">
                                    {{ $item['label'] }} <x-icon name="chevron-right" class="size-5 text-stone-400" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($navCategories->isNotEmpty())
                        <p class="eyebrow mt-6 text-stone-400">Kategori</p>
                        <ul class="mt-2">
                            @foreach ($navCategories as $category)
                                <li><a href="{{ route('products.index', ['kategori' => $category->slug]) }}" class="block py-2.5 text-[15px] font-medium">{{ $category->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </nav>
                <div class="border-t border-stone-200 bg-sand-50 p-4">
                    @auth
                        <p class="text-sm font-semibold">{{ $user->name }}</p>
                        <p class="text-xs text-stone-500">{{ $user->email }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            @if ($user->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-ink btn-pill col-span-2 py-2.5">Admin Panel</a>
                            @else
                                <a href="{{ route('account.dashboard') }}" class="btn btn-ink btn-pill py-2.5">Akun saya</a>
                                <a href="{{ route('account.orders.index') }}" class="btn btn-outline btn-pill py-2.5">Pesanan</a>
                                <a href="{{ route('account.wishlist') }}" class="btn btn-outline btn-pill col-span-2 py-2.5"><x-icon name="heart" class="size-4" /> Wishlist ({{ $wishlistCount }})</a>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="mt-3">
                            @csrf
                            <button class="text-sm font-semibold text-rose-600">Keluar</button>
                        </form>
                    @else
                        <p class="text-sm text-stone-600">Masuk untuk checkout dan melacak pesananmu.</p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <a href="{{ route('login') }}" class="btn btn-ink btn-pill py-2.5">Masuk</a>
                            <a href="{{ route('register') }}" class="btn btn-outline btn-pill py-2.5">Daftar</a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>

        <main class="flex-1">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="mt-20 bg-ink text-white">
            <div class="shop-container py-14 lg:py-20">
                <div class="grid gap-12 lg:grid-cols-12">
                    <div class="lg:col-span-5">
                        <a href="{{ route('home') }}" class="mb-8 inline-block" aria-label="{{ config('shop.full_name') }}"><x-logo dark class="h-14" /></a>
                        <h2 class="display text-3xl leading-[0.95] sm:text-4xl">Butuh bantuan memilih?</h2>
                        <p class="mt-4 max-w-sm text-sm leading-relaxed text-white/60">{{ config('shop.tagline') }}. Tim kami siap membantu dari pemilihan produk sampai pesanan tiba.</p>
                        @if (config('shop.whatsapp'))
                            <a href="https://wa.me/{{ config('shop.whatsapp') }}" target="_blank" rel="noopener" class="btn btn-light btn-pill mt-6">
                                <x-icon name="chat" class="size-4" /> Chat WhatsApp
                            </a>
                        @else
                            <a href="{{ route('products.index') }}" class="btn btn-light btn-pill mt-6">Mulai belanja <x-icon name="arrow-right" class="size-4" /></a>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-7">
                        <div>
                            <h3 class="eyebrow text-white/40">Belanja</h3>
                            <ul class="mt-4 space-y-2.5 text-sm text-white/80">
                                <li><a href="{{ route('products.index') }}" class="hover:text-white hover:underline hover:underline-offset-4">Semua produk</a></li>
                                @foreach ($navCategories->take(5) as $category)
                                    <li><a href="{{ route('products.index', ['kategori' => $category->slug]) }}" class="hover:text-white hover:underline hover:underline-offset-4">{{ $category->name }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                        <div>
                            <h3 class="eyebrow text-white/40">Bantuan</h3>
                            <ul class="mt-4 space-y-2.5 text-sm text-white/80">
                                <li><a href="{{ auth()->check() ? route('account.orders.index') : route('login') }}" class="hover:text-white hover:underline hover:underline-offset-4">Lacak pesanan</a></li>
                                <li><a href="{{ $accountUrl }}" class="hover:text-white hover:underline hover:underline-offset-4">Akun saya</a></li>
                                <li><a href="{{ route('cart.index') }}" class="hover:text-white hover:underline hover:underline-offset-4">Keranjang</a></li>
                                @if (config('shop.whatsapp'))
                                    <li><a href="https://wa.me/{{ config('shop.whatsapp') }}" target="_blank" rel="noopener" class="hover:text-white hover:underline hover:underline-offset-4">WhatsApp</a></li>
                                @endif
                            </ul>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <h3 class="eyebrow text-white/40">Kenapa MGA</h3>
                            <ul class="mt-4 space-y-2.5 text-sm text-white/80">
                                <li class="flex items-center gap-2"><x-icon name="shield" class="size-4 text-white/50" /> Original & bergaransi</li>
                                <li class="flex items-center gap-2"><x-icon name="truck" class="size-4 text-white/50" /> Kirim se-Indonesia</li>
                                <li class="flex items-center gap-2"><x-icon name="lock" class="size-4 text-white/50" /> Pembayaran aman</li>
                            </ul>
                            @if (config('shop.instagram'))
                                <a href="https://instagram.com/{{ ltrim(config('shop.instagram'), '@') }}" target="_blank" rel="noopener" class="mt-5 inline-block text-sm text-white/80 hover:text-white hover:underline hover:underline-offset-4">Instagram &#64;{{ ltrim(config('shop.instagram'), '@') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-hidden border-t border-white/10">
                <div class="shop-container pt-10" aria-hidden="true">
                    <x-logo variant="mark" mono class="h-auto w-[78%] text-white/[0.06] lg:w-[60%]" />
                </div>
            </div>
            <div class="border-t border-white/10">
                <div class="shop-container flex flex-col gap-2 py-5 text-xs text-white/40 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ date('Y') }} {{ config('shop.full_name') }}. Semua hak dilindungi.</p>
                    <p>Transfer bank · E-wallet · QRIS · Kartu kredit</p>
                </div>
            </div>
        </footer>
    </div>
</x-layouts.base>
