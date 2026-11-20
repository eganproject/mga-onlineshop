@use('App\Support\Money')
@php
    $fmt = fn (int $n) => number_format($n, 0, ',', '.');
    $reportQuery = $period->toQuery();
    $user = auth()->user();
@endphp
<x-layouts.admin title="Dashboard">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-medium text-stone-500 sm:text-sm">{{ now()->translatedFormat('l, d F Y') }}</p>
            <h1 class="font-display text-xl font-semibold tracking-tight text-stone-900 sm:text-[1.4rem]">Halo, {{ \Illuminate\Support\Str::words(auth()->user()->name, 1, '') }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Periode hanya mengubah angka penjualan, jadi tanpa akses laporan tidak ditampilkan. --}}
            @if ($user->can('reports.view'))
            <nav class="inline-flex rounded-lg bg-stone-200/60 p-0.5 text-xs font-medium" aria-label="Periode dashboard">
                @foreach (\App\Http\Controllers\Admin\DashboardController::RANGES as $key => $label)
                    <a href="{{ route('admin.dashboard', ['range' => $key]) }}" @if ($range === $key) aria-current="page" @endif
                       @class(['rounded-md px-3 py-1.5 transition', 'bg-white font-semibold text-stone-900 shadow-xs' => $range === $key, 'text-stone-500 hover:text-stone-900' => $range !== $key])>{{ $label }}</a>
                @endforeach
            </nav>
            @endif
            @can('reports.view')
                <a href="{{ route('admin.reports.index', $reportQuery) }}" class="btn btn-secondary"><x-icon name="chart" class="size-4" /> Laporan</a>
            @endcan
        </div>
    </div>

    {{-- Perlu tindakan: hal yang menunggu staf (sesuai hak akses), paling atas supaya langsung terlihat. --}}
    @if ($actions)
        <section aria-label="Perlu tindakan" @class(['card mb-4 grid grid-cols-1 gap-px overflow-hidden bg-stone-200 sm:grid-cols-2', 'xl:grid-cols-4' => count($actions) === 4, 'xl:grid-cols-3' => count($actions) === 3])>
            @foreach ($actions as $action)
                <a href="{{ $action['url'] }}" @class([
                    'group flex items-center gap-3 bg-white px-4 py-3 transition hover:bg-stone-50',
                    'sm:col-span-2 xl:col-span-1' => count($actions) % 2 === 1 && $loop->last,
                ])>
                    <span @class([
                        'min-w-7 text-xl font-semibold tabular-nums',
                        'text-stone-300' => $action['count'] === 0,
                        'text-sky-600' => $action['count'] > 0 && $action['tone'] === 'sky',
                        'text-amber-600' => $action['count'] > 0 && $action['tone'] === 'amber',
                        'text-rose-600' => $action['count'] > 0 && $action['tone'] === 'rose',
                        'text-stone-900' => $action['count'] > 0 && $action['tone'] === 'stone',
                    ])>{{ $fmt($action['count']) }}</span>
                    <span class="min-w-0 flex-1 text-sm leading-snug">
                        <span class="block font-semibold text-stone-800">{{ $action['title'] }}</span>
                        <span class="block truncate text-xs text-stone-500">{{ $action['hint'] }}</span>
                    </span>
                    <x-icon name="chevron-right" class="size-4 shrink-0 text-stone-300 transition group-hover:translate-x-0.5 group-hover:text-stone-500" />
                </a>
            @endforeach
        </section>
    @endif

    {{-- Angka penjualan hanya untuk jabatan yang boleh melihat laporan: satu panel, tanpa kartu berwarna. --}}
    @can('reports.view')
    @php
        $kpis = [
            ['Pendapatan', Money::rupiah($summary['revenue']), $changes['revenue']],
            ['Pesanan dibayar', $fmt($summary['orders']), $changes['orders']],
            ['Rata-rata per pesanan', Money::rupiah($summary['average']), $changes['average']],
            ['Produk terjual', $fmt($summary['items']).' pcs', $changes['items']],
        ];
    @endphp
    <section aria-label="Ringkasan penjualan" class="card grid grid-cols-2 gap-px overflow-hidden bg-stone-200 xl:grid-cols-4">
        @foreach ($kpis as [$label, $value, $change])
            <div class="min-w-0 bg-white p-4">
                <p class="text-xs font-medium text-stone-500">{{ $label }}</p>
                <p class="mt-1 truncate text-lg font-semibold tracking-tight text-stone-900 tabular-nums sm:text-xl">{{ $value }}</p>
                <p class="mt-0.5 text-xs text-stone-500">
                    @if (is_null($change))
                        Belum ada pembanding
                    @else
                        <span @class(['font-semibold', 'text-emerald-600' => $change > 0, 'text-rose-600' => $change < 0, 'text-stone-600' => $change == 0])>{{ $change > 0 ? '+' : ($change < 0 ? '−' : '') }}{{ str_replace('.', ',', (string) abs($change)) }}%</span>
                        <span class="hidden sm:inline">vs periode sebelumnya</span>
                    @endif
                </p>
            </div>
        @endforeach
    </section>
    <p class="mt-2 text-xs text-stone-400">Periode {{ $period->label() }}, dibandingkan {{ $period->days() }} hari sebelumnya. Dihitung dari pesanan yang sudah dibayar.</p>
    @endcan

    {{-- Tanpa akses laporan: pesanan berjalan & stok menipis tampil berdampingan dalam satu grid. --}}
    <div @class(['mt-4 grid gap-4', 'xl:grid-cols-3' => auth()->user()->can('reports.view'), 'lg:grid-cols-2' => auth()->user()->cannot('reports.view')])>
        {{-- Grafik penjualan --}}
        @can('reports.view')
        <div class="card min-w-0 p-5 xl:col-span-2">
            <x-admin.sales-chart :series="$series">
                <x-slot:title>
                    <h2 class="font-bold text-stone-900">Tren penjualan</h2>
                    <p class="text-xs text-stone-500">{{ $period->group === 'week' ? 'Per minggu' : 'Per hari' }} · {{ $period->label() }}</p>
                </x-slot:title>
            </x-admin.sales-chart>
        </div>
        @endcan

        {{-- Pesanan berjalan --}}
        @can('orders.view')
        <div class="card min-w-0 p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-stone-900">Pesanan berjalan</h2>
                <span class="text-xs text-stone-500">{{ $ordersToday }} pesanan masuk hari ini</span>
            </div>
            <ul class="mt-4 space-y-2">
                @foreach ($pipeline as $row)
                    <li>
                        <a href="{{ route('admin.orders.index', ['status' => $row['status']->value]) }}" class="flex items-center justify-between gap-3 rounded-xl border border-stone-100 px-3 py-2.5 transition hover:border-stone-200 hover:bg-stone-50">
                            <span class="flex items-center gap-2 text-sm text-stone-700">
                                <x-order-status :status="$row['status']" />
                                @if ($row['status'] === \App\Enums\OrderStatus::Paid && $row['count'] > 0)
                                    <span class="text-xs font-semibold text-brand-600">perlu diproses</span>
                                @endif
                            </span>
                            <span class="text-base font-semibold tabular-nums text-stone-900">{{ $fmt($row['count']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 grid grid-cols-2 gap-3 border-t border-stone-100 pt-4 text-sm">
                <div>
                    <p class="text-xs text-stone-500">Pelanggan</p>
                    <p class="font-bold text-stone-900 tabular-nums">{{ $fmt($customers['total']) }}</p>
                </div>
                <div>
                    <p class="text-xs text-stone-500">Pelanggan baru ({{ $period->days() }} hari)</p>
                    <p class="font-bold text-stone-900 tabular-nums">+{{ $fmt($customers['new']) }}</p>
                </div>
            </div>
        </div>
        @endcan
    @can('reports.view')
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
        {{-- Produk terlaris --}}
        <div class="card min-w-0 p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-stone-900">Produk terlaris</h2>
                <a href="{{ route('admin.reports.index', ['tab' => 'produk'] + $reportQuery) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Lihat semua</a>
            </div>
            @if ($topProducts->isEmpty())
                <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm text-stone-500">Belum ada penjualan di periode ini.</p>
            @else
                <ol class="mt-4 space-y-3">
                    @foreach ($topProducts as $i => $row)
                        <li class="flex items-center gap-3">
                            <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-stone-100 text-xs font-bold text-stone-600">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                @if ($row->slug && auth()->user()->can('catalog.manage'))
                                    <a href="{{ route('admin.products.edit', $row->slug) }}" class="block truncate text-sm font-semibold text-stone-800 hover:text-brand-700">{{ $row->name }}</a>
                                @else
                                    <p class="truncate text-sm font-semibold text-stone-800">{{ $row->name }}</p>
                                @endif
                                <p class="text-xs text-stone-500">{{ $fmt($row->quantity) }} pcs terjual</p>
                            </div>
                            <span class="text-sm font-semibold tabular-nums text-stone-900">{{ Money::compact($row->revenue) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        {{-- Penjualan per kategori --}}
        <div class="card min-w-0 p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-stone-900">Penjualan per kategori</h2>
                <a href="{{ route('admin.reports.index', ['tab' => 'kategori'] + $reportQuery) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Lihat semua</a>
            </div>
            @if ($categories->isEmpty())
                <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm text-stone-500">Belum ada penjualan di periode ini.</p>
            @else
                <ul class="mt-4 space-y-3.5">
                    @foreach ($categories as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate font-medium text-stone-700">{{ $row->name }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-stone-900">{{ Money::compact($row->revenue) }} <span class="font-normal text-stone-400">· {{ str_replace('.', ',', (string) $row->share) }}%</span></span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-stone-100">
                                <div class="h-2 rounded-full bg-stone-400" style="width: {{ max(2, $row->share) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @endcan

        {{-- Stok menipis --}}
        @can('stock.manage')
        <div @class(['card min-w-0 p-5', 'lg:col-span-2 xl:col-span-1' => auth()->user()->can('reports.view')])>
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-stone-900">Stok menipis @if ($lowStockCount) <span class="ml-1 badge bg-rose-50 text-rose-700 ring-rose-600/20">{{ $lowStockCount }}</span> @endif</h2>
                <a href="{{ route('admin.stock.index', ['status' => 'menipis']) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Kelola stok</a>
            </div>
            <ul class="mt-4 space-y-3">
                @forelse ($lowStock as $item)
                    <li class="flex items-center gap-3">
                        <x-product-image :product="$item->product" class="size-11 shrink-0 rounded-xl" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ auth()->user()->can('catalog.manage') ? route('admin.products.edit', $item->product) : route('admin.stock.index', ['q' => $item->sku ?? $item->name]) }}" class="block truncate text-sm font-semibold text-stone-800 hover:text-brand-700">{{ $item->name }}</a>
                            <p class="truncate text-xs text-stone-500">{{ $item->variant ? $item->variant->name.' · ' : '' }}{{ $item->sku ?? 'Tanpa SKU' }}</p>
                        </div>
                        <span @class(['badge shrink-0', 'bg-rose-50 text-rose-700 ring-rose-600/20' => $item->stock === 0, 'bg-amber-50 text-amber-700 ring-amber-600/20' => $item->stock > 0])>
                            {{ $item->stock === 0 ? 'Habis' : $item->stock.' pcs' }}
                        </span>
                    </li>
                @empty
                    <li class="rounded-lg bg-stone-50 p-4 text-sm text-stone-500">Semua stok aman.</li>
                @endforelse
            </ul>
        </div>
        @endcan
    </div>

    {{-- Pesanan terbaru --}}
    @can('orders.view')
    <div class="card mt-4 overflow-hidden">
        <div class="flex items-center justify-between p-5">
            <h2 class="font-bold text-stone-900">Pesanan terbaru</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Lihat semua</a>
        </div>
        @if ($recentOrders->isEmpty())
            <x-empty-state icon="receipt" title="Belum ada pesanan" description="Pesanan dari pelanggan akan muncul di sini." />
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Pesanan</th><th class="hidden sm:table-cell">Pelanggan</th><th>Total</th><th class="hidden sm:table-cell">Status</th><th class="hidden sm:table-cell"></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($recentOrders as $order)
                            <tr class="hover:bg-stone-50/50">
                                <td class="sm:whitespace-nowrap"><a href="{{ route('admin.orders.show', $order) }}" class="whitespace-nowrap font-semibold text-stone-900 hover:text-brand-700">{{ $order->code }}</a><p class="text-xs text-stone-500">{{ $order->created_at->diffForHumans() }}<span class="sm:hidden"> · {{ $order->customerName() }}</span></p><div class="mt-1 sm:hidden"><x-order-status :status="$order->status" /></div></td>
                                <td class="hidden sm:table-cell">{{ $order->customerName() }}</td>
                                <td class="font-semibold whitespace-nowrap tabular-nums">@rupiah($order->total)</td>
                                <td class="hidden sm:table-cell"><x-order-status :status="$order->status" /></td>
                                <td class="hidden text-right sm:table-cell"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endcan

    {{-- Jabatan tanpa pesanan, laporan & stok (mis. Marketing): arahkan ke menu samping. --}}
    @unless (auth()->user()->canAny(['orders.view', 'reports.view', 'stock.manage']))
        <div class="card mt-6 flex items-start gap-3 p-5 text-sm text-stone-600">
            <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-brand-600" />
            @if (collect(\App\Enums\Permission::cases())->contains(fn ($permission) => auth()->user()->hasPermission($permission)))
                <p>Pilih menu di samping untuk mulai bekerja. Menu yang tampil sesuai hak akses jabatan Anda ({{ auth()->user()->staffRole()?->label() }}).</p>
            @else
                {{-- Jabatan tanpa izin apa pun (atau jabatannya sudah dihapus): jangan biarkan staf bingung di halaman kosong. --}}
                <div>
                    <p class="font-semibold text-stone-800">Jabatan Anda belum punya hak akses ke menu apa pun.</p>
                    <p class="mt-1">Minta Superadmin mencentang hak akses jabatan {{ auth()->user()->staffRole()?->label() ?? 'Anda' }} di Admin › Jabatan & Akses. Sementara itu Anda tetap bisa mengubah <a href="{{ route('admin.profile.edit') }}" class="font-semibold text-brand-700 underline">profil & password</a>.</p>
                </div>
            @endif
        </div>
    @endunless
</x-layouts.admin>
