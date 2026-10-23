@props(['title' => null])
@php
    $user = auth()->user();
    $pendingOrders = \App\Models\Order::where('status', \App\Enums\OrderStatus::Paid)->count();
    $menu = [
        'Utama' => [
            ['Dashboard', 'admin.dashboard', 'dashboard', 'admin.dashboard', null],
            ['Pesanan', 'admin.orders.index', 'receipt', 'admin.orders.*', $pendingOrders ?: null],
        ],
        'Katalog' => [
            ['Produk', 'admin.products.index', 'cube', 'admin.products.*', null],
            ['Kategori', 'admin.categories.index', 'tag', 'admin.categories.*', null],
            ['Stok', 'admin.stock.index', 'archive', 'admin.stock.*', null],
        ],
        'Pelanggan' => [
            ['Pelanggan', 'admin.customers.index', 'users', 'admin.customers.*', null],
        ],
        'Sistem' => [
            ['Integrasi', 'admin.settings', 'cog', 'admin.settings', null],
        ],
    ];
@endphp
<x-layouts.base :title="$title ? $title.' · Admin' : 'Admin'" body-class="bg-cream text-stone-800">
    <div x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
        {{-- Overlay mobile --}}
        <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-40 bg-stone-900/40 backdrop-blur-sm lg:hidden"></div>

        {{-- Sidebar --}}
        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-stone-200/70 bg-white transition-transform duration-300 lg:translate-x-0">
            <div class="flex h-16 items-center justify-between px-6">
                <a href="{{ route('admin.dashboard') }}"><x-logo /></a>
                <button @click="sidebar = false" class="btn btn-ghost p-2 lg:hidden" aria-label="Tutup menu"><x-icon name="x" /></button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-4">
                @foreach ($menu as $group => $items)
                    <div>
                        <p class="mb-2 px-3 text-[11px] font-bold tracking-wider text-stone-400 uppercase">{{ $group }}</p>
                        <ul class="space-y-1">
                            @foreach ($items as [$label, $route, $icon, $pattern, $badge])
                                @php($active = request()->routeIs($pattern))
                                <li>
                                    <a href="{{ route($route) }}"
                                       @class([
                                           'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                           'bg-brand-50 text-brand-700' => $active,
                                           'text-stone-600 hover:bg-stone-50 hover:text-stone-900' => ! $active,
                                       ])>
                                        <x-icon :name="$icon" @class(['size-5', 'text-brand-600' => $active, 'text-stone-400 group-hover:text-stone-600' => ! $active]) />
                                        <span class="flex-1">{{ $label }}</span>
                                        @if ($badge)
                                            <span class="rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ $badge }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="m-4 rounded-2xl bg-gradient-to-br from-brand-50 to-amber-50 p-4">
                <p class="text-sm font-bold text-stone-900">Lihat toko</p>
                <p class="mt-0.5 text-xs text-stone-500">Cek tampilan toko seperti yang dilihat pelanggan.</p>
                <a href="{{ route('home') }}" target="_blank" class="btn btn-secondary btn-sm mt-3 w-full">
                    <x-icon name="external" class="size-4" /> Buka toko
                </a>
            </div>
        </aside>

        {{-- Konten --}}
        <div class="min-w-0 overflow-x-clip lg:pl-72">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-stone-200/70 bg-cream/85 px-4 backdrop-blur-lg sm:px-6 lg:px-8">
                <button @click="sidebar = true" class="btn btn-ghost -ml-2 p-2 lg:hidden" aria-label="Buka menu"><x-icon name="menu" /></button>

                <form action="{{ route('admin.orders.index') }}" method="GET" class="relative hidden max-w-xs flex-1 sm:block">
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" name="q" placeholder="Cari kode pesanan / pelanggan..." class="input rounded-full border-transparent bg-white pl-10 shadow-none">
                </form>

                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('admin.orders.index', ['status' => 'paid']) }}" class="btn btn-ghost relative p-2.5" aria-label="Pesanan baru">
                        <x-icon name="bell" />
                        @if ($pendingOrders)
                            <span class="absolute right-2 top-2 size-2 rounded-full bg-brand-600 ring-2 ring-cream"></span>
                        @endif
                    </a>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2.5 rounded-full p-1 pr-2 transition hover:bg-white sm:pr-3">
                            <span class="grid size-9 place-items-center rounded-full bg-stone-900 text-xs font-bold text-white">{{ $user->initials() }}</span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-sm font-semibold leading-tight text-stone-800">{{ $user->name }}</span>
                                <span class="block text-xs text-stone-500">{{ $user->role->label() }}</span>
                            </span>
                            <x-icon name="chevron-down" class="size-4 text-stone-400" />
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-52 rounded-2xl border border-stone-200 bg-white p-1.5 shadow-xl shadow-stone-900/10">
                            <a href="{{ route('admin.profile.edit') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-50"><x-icon name="user" class="size-4" /> Profil saya</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50"><x-icon name="logout" class="size-4" /> Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
