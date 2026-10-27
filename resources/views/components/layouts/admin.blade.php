@props(['title' => null])
@php
    $user = auth()->user();
    // Angka badge hanya dihitung untuk menu yang boleh dibuka.
    $pendingOrders = $user->can('orders.view') ? \App\Models\Order::where('status', \App\Enums\OrderStatus::Paid)->count() : 0;
    $pendingReviews = $user->can('reviews.manage') ? \App\Models\ProductReview::where('status', \App\Enums\ReviewStatus::Pending)->count() : 0;
    // Sidebar ciut (ikon saja) di desktop; diingat lewat cookie supaya tidak berkedip saat halaman dimuat.
    $collapsed = request()->cookie('admin_sidebar') === 'collapsed';
    $menu = [
        'Utama' => [
            ['Dashboard', 'admin.dashboard', 'dashboard', 'admin.dashboard', null],
            ['Pesanan', 'admin.orders.index', 'receipt', 'admin.orders.*', $pendingOrders ?: null],
            ['Laporan', 'admin.reports.index', 'chart', 'admin.reports.*', null],
        ],
        'Katalog' => [
            ['Produk', 'admin.products.index', 'cube', 'admin.products.*', null],
            ['Kategori', 'admin.categories.index', 'tag', 'admin.categories.*', null],
            ['Stok', 'admin.stock.index', 'archive', 'admin.stock.*', null],
            ['Ulasan', 'admin.reviews.index', 'star', 'admin.reviews.*', $pendingReviews ?: null],
        ],
        'Promosi' => [
            ['Voucher', 'admin.vouchers.index', 'ticket', 'admin.vouchers.*', null],
            ['Banner', 'admin.banners.index', 'megaphone', 'admin.banners.*', null],
            ['Tampilan Beranda', 'admin.storefront.edit', 'photo', 'admin.storefront.*', null],
        ],
        'Konten' => [
            ['Artikel', 'admin.articles.index', 'newspaper', 'admin.articles.*', null],
            ['Kategori Artikel', 'admin.article-categories.index', 'list-bullet', 'admin.article-categories.*', null],
        ],
        'Pelanggan' => [
            ['Pelanggan', 'admin.customers.index', 'users', 'admin.customers.*', null],
        ],
        'Sistem' => [
            ['Pengaturan Toko', 'admin.store-settings.edit', 'storefront', 'admin.store-settings.*', null],
            ['Pajak & Biaya', 'admin.billing.edit', 'percent', 'admin.billing.*', null],
            ['Integrasi', 'admin.integrations.index', 'cog', 'admin.integrations.*', null],
            ['Staf', 'admin.staff.index', 'shield', 'admin.staff.*', null],
            ['Log Aktivitas', 'admin.activity.index', 'clock', 'admin.activity.*', null],
        ],
    ];
    // Sembunyikan menu yang tidak boleh dibuka jabatan staf ini (dibaca dari middleware `can:` route).
    $menu = array_filter(array_map(fn (array $items) => array_values(array_filter($items, fn (array $item) => \App\Support\AdminAccess::canVisit($item[1]))), $menu));
@endphp
<x-layouts.base :title="$title ? $title.' · Admin' : 'Admin'" body-class="bg-cream text-stone-800">
    <div x-data="{
             sidebar: false,
             collapsed: @js($collapsed),
             tip: null,
             tipTop: 0,
             toggleCollapsed() {
                 this.collapsed = ! this.collapsed;
                 this.tip = null;
                 document.cookie = 'admin_sidebar=' + (this.collapsed ? 'collapsed' : 'expanded') + '; path=/admin; max-age=31536000; SameSite=Lax';
             },
             showTip(el, label) {
                 if (! this.collapsed || ! window.matchMedia('(min-width: 1024px)').matches) return;
                 const rect = el.getBoundingClientRect();
                 this.tipTop = rect.top + rect.height / 2;
                 this.tip = label;
             },
         }"
         @keydown.escape.window="sidebar = false">
        {{-- Overlay mobile --}}
        <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-40 bg-stone-900/40 backdrop-blur-sm lg:hidden"></div>

        {{-- Sidebar: laci di HP, bisa diciutkan jadi ikon saja di desktop --}}
        <aside :class="{ 'translate-x-0': sidebar, '-translate-x-full': ! sidebar, 'lg:w-20': collapsed, 'lg:w-72': ! collapsed }"
               @class(['fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-stone-200/70 bg-white transition-[transform,width] duration-300 lg:translate-x-0', 'lg:w-20' => $collapsed, 'lg:w-72' => ! $collapsed])
               aria-label="Menu admin">
            <div :class="{ 'lg:justify-center lg:px-0': collapsed, 'px-6': true }" @class(['flex h-16 shrink-0 items-center justify-between px-6', 'lg:justify-center lg:px-0' => $collapsed])>
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3" aria-label="Dashboard admin">
                    <x-logo x-bind:class="{ 'lg:hidden': collapsed }" @class(['h-9', 'lg:hidden' => $collapsed]) />
                    <x-logo variant="mark" x-bind:class="{ 'lg:block': collapsed }" @class(['hidden h-auto w-12', 'lg:block' => $collapsed]) />
                    <span :class="{ 'lg:hidden': collapsed }" @class(['rounded-md bg-brand-50 px-1.5 py-0.5 text-[10px] font-bold tracking-wider text-brand-700 uppercase', 'lg:hidden' => $collapsed])>Admin</span>
                </a>
                <button @click="sidebar = false" class="btn btn-ghost p-2 lg:hidden" aria-label="Tutup menu"><x-icon name="x" /></button>
            </div>

            <nav class="no-scrollbar flex-1 space-y-5 overflow-y-auto px-3 py-3" @scroll="tip = null">
                @foreach ($menu as $group => $items)
                    <div>
                        <p :class="{ 'lg:hidden': collapsed }" @class(['mb-1.5 px-3 text-[11px] font-bold tracking-wider text-stone-400 uppercase', 'lg:hidden' => $collapsed])>{{ $group }}</p>
                        <div :class="{ 'lg:block': collapsed }" @class(['mx-3 mb-2 hidden border-t border-stone-100', 'lg:block' => $collapsed]) aria-hidden="true"></div>
                        <ul class="space-y-0.5">
                            @foreach ($items as [$label, $route, $icon, $pattern, $badge])
                                @php($active = request()->routeIs($pattern))
                                <li>
                                    <a href="{{ route($route) }}"
                                       @if ($active) aria-current="page" @endif
                                       @mouseenter="showTip($el, @js($label.($badge ? " ({$badge})" : '')))" @mouseleave="tip = null"
                                       @focus="showTip($el, @js($label))" @blur="tip = null"
                                       :class="{ 'lg:justify-center lg:px-0': collapsed }"
                                       @class([
                                           'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                           'lg:justify-center lg:px-0' => $collapsed,
                                           'bg-brand-50 text-brand-700' => $active,
                                           'text-stone-600 hover:bg-stone-50 hover:text-stone-900' => ! $active,
                                       ])>
                                        <x-icon :name="$icon" @class(['size-5 shrink-0', 'text-brand-600' => $active, 'text-stone-400 group-hover:text-stone-600' => ! $active]) />
                                        <span :class="{ 'lg:sr-only': collapsed }" @class(['flex-1 truncate', 'lg:sr-only' => $collapsed])>{{ $label }}</span>
                                        @if ($badge)
                                            <span :class="{ 'lg:hidden': collapsed }" @class(['rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white', 'lg:hidden' => $collapsed])>{{ $badge }}</span>
                                            <span :class="{ 'lg:block': collapsed }" @class(['absolute right-3 top-2 hidden size-2 rounded-full bg-brand-600 ring-2 ring-white', 'lg:block' => $collapsed]) aria-hidden="true"></span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="shrink-0 space-y-0.5 border-t border-stone-100 p-3">
                <a href="{{ route('home') }}" target="_blank" rel="noopener"
                   @mouseenter="showTip($el, 'Buka toko')" @mouseleave="tip = null"
                   :class="{ 'lg:justify-center lg:px-0': collapsed }"
                   @class(['group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-stone-600 transition hover:bg-stone-50 hover:text-stone-900', 'lg:justify-center lg:px-0' => $collapsed])>
                    <x-icon name="external" class="size-5 shrink-0 text-stone-400 group-hover:text-stone-600" />
                    <span :class="{ 'lg:sr-only': collapsed }" @class(['flex-1', 'lg:sr-only' => $collapsed])>Buka toko</span>
                </a>
                <button type="button" @click="toggleCollapsed()"
                        @mouseenter="showTip($el, 'Lebarkan menu')" @mouseleave="tip = null"
                        :aria-expanded="(! collapsed).toString()" aria-label="Ciutkan / lebarkan menu"
                        :class="{ 'lg:justify-center lg:px-0': collapsed }"
                        @class(['group hidden w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-stone-500 transition hover:bg-stone-50 hover:text-stone-900 lg:flex', 'lg:justify-center lg:px-0' => $collapsed])>
                    <x-icon name="chevrons-left" x-bind:class="{ 'rotate-180': collapsed }" @class(['size-5 shrink-0 text-stone-400 transition-transform group-hover:text-stone-600', 'rotate-180' => $collapsed]) />
                    <span :class="{ 'lg:sr-only': collapsed }" @class(['flex-1 text-left', 'lg:sr-only' => $collapsed])>Ciutkan menu</span>
                </button>
            </div>
        </aside>

        {{-- Tooltip menu saat sidebar ciut --}}
        <div x-show="tip && collapsed" x-cloak x-transition.opacity.duration.100ms
             class="pointer-events-none fixed left-[5.25rem] z-[60] hidden -translate-y-1/2 rounded-lg bg-stone-900 px-2.5 py-1.5 text-xs font-semibold whitespace-nowrap text-white shadow-lg lg:block"
             :style="`top: ${tipTop}px`" x-text="tip" role="tooltip"></div>

        {{-- Konten --}}
        <div :class="{ 'lg:pl-20': collapsed, 'lg:pl-72': ! collapsed }" @class(['min-w-0 overflow-x-clip transition-[padding] duration-300', 'lg:pl-20' => $collapsed, 'lg:pl-72' => ! $collapsed])>
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-stone-200/70 bg-cream/85 px-4 backdrop-blur-lg sm:px-6 lg:px-8">
                <button @click="sidebar = true" class="btn btn-ghost -ml-2 p-2 lg:hidden" aria-label="Buka menu"><x-icon name="menu" /></button>

                @can('orders.view')
                <form action="{{ route('admin.orders.index') }}" method="GET" class="relative hidden max-w-xs flex-1 sm:block">
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" name="q" placeholder="Cari kode pesanan / pelanggan..." class="input rounded-full border-transparent bg-white pl-10 shadow-none">
                </form>
                @endcan

                <div class="ml-auto flex items-center gap-2">
                    @can('orders.view')
                    <a href="{{ route('admin.orders.index', ['status' => 'paid']) }}" class="btn btn-ghost relative p-2.5" aria-label="Pesanan baru">
                        <x-icon name="bell" />
                        @if ($pendingOrders)
                            <span class="absolute right-2 top-2 size-2 rounded-full bg-brand-600 ring-2 ring-cream"></span>
                        @endif
                    </a>
                    @endcan
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2.5 rounded-full p-1 pr-2 transition hover:bg-white sm:pr-3">
                            <span class="grid size-9 place-items-center rounded-full bg-stone-900 text-xs font-bold text-white">{{ $user->initials() }}</span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-sm font-semibold leading-tight text-stone-800">{{ $user->name }}</span>
                                <span class="block text-xs text-stone-500">{{ $user->staffRole()?->label() ?? $user->role->label() }}</span>
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
