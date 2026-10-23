@use('App\Support\Money')
@php
    $fmt = fn (int $n) => number_format($n, 0, ',', '.');
    $reportQuery = $period->toQuery();
@endphp
<x-layouts.admin title="Dashboard">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-stone-500">{{ now()->translatedFormat('l, d F Y') }}</p>
            <h1 class="text-2xl font-extrabold tracking-tight text-stone-900">Halo, {{ \Illuminate\Support\Str::words(auth()->user()->name, 1, '') }}! ☕</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <nav class="inline-flex rounded-xl border border-stone-200 bg-white p-1 text-xs font-semibold" aria-label="Periode dashboard">
                @foreach (\App\Http\Controllers\Admin\DashboardController::RANGES as $key => $label)
                    <a href="{{ route('admin.dashboard', ['range' => $key]) }}" @if ($range === $key) aria-current="page" @endif
                       @class(['rounded-lg px-3 py-1.5 transition', 'bg-stone-900 text-white' => $range === $key, 'text-stone-500 hover:text-stone-900' => $range !== $key])>{{ $label }}</a>
                @endforeach
            </nav>
            @can('reports.view')
                <a href="{{ route('admin.reports.index', $reportQuery) }}" class="btn btn-secondary"><x-icon name="chart" class="size-4" /> Laporan</a>
            @endcan
        </div>
    </div>

    {{-- Angka penjualan hanya untuk jabatan yang boleh melihat laporan. --}}
    @can('reports.view')
    {{-- KPI --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat-card label="Pendapatan" :value="Money::rupiah($summary['revenue'])" icon="banknotes" :change="$changes['revenue']" />
        <x-admin.stat-card label="Pesanan dibayar" :value="$fmt($summary['orders'])" icon="receipt" tone="sky" :change="$changes['orders']" />
        <x-admin.stat-card label="Rata-rata per pesanan" :value="Money::rupiah($summary['average'])" icon="trending-up" tone="violet" :change="$changes['average']" />
        <x-admin.stat-card label="Produk terjual" :value="$fmt($summary['items']).' pcs'" icon="cube" tone="emerald" :change="$changes['items']" />
    </div>
    <p class="mt-2 text-xs text-stone-400">Periode {{ $period->label() }}, dibandingkan {{ $period->days() }} hari sebelumnya. Dihitung dari pesanan yang sudah dibayar.</p>
    @endcan

    {{-- Tanpa akses laporan: pesanan berjalan & stok menipis tampil berdampingan dalam satu grid. --}}
    <div @class(['mt-6 grid gap-6', 'xl:grid-cols-3' => auth()->user()->can('reports.view'), 'lg:grid-cols-2' => auth()->user()->cannot('reports.view')])>
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
                            <span class="text-lg font-extrabold tabular-nums text-stone-900">{{ $fmt($row['count']) }}</span>
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
                    <p class="text-xs text-stone-500">Pelanggan baru</p>
                    <p class="font-bold text-stone-900 tabular-nums">+{{ $fmt($customers['new']) }}</p>
                </div>
            </div>
        </div>
    @can('reports.view')
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
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
                                @if ($row->product_id)
                                    <a href="{{ route('admin.products.edit', $row->product_id) }}" class="block truncate text-sm font-semibold text-stone-800 hover:text-brand-700">{{ $row->name }}</a>
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
                                <div class="h-2 rounded-full bg-brand-500" style="width: {{ max(2, $row->share) }}%"></div>
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
                    <li class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">Semua stok aman 👍</li>
                @endforelse
            </ul>
        </div>
        @endcan
    </div>

    {{-- Pesanan terbaru --}}
    <div class="card mt-6 overflow-hidden">
        <div class="flex items-center justify-between p-5">
            <h2 class="font-bold text-stone-900">Pesanan terbaru</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Lihat semua</a>
        </div>
        @if ($recentOrders->isEmpty())
            <x-empty-state icon="receipt" title="Belum ada pesanan" description="Pesanan dari pelanggan akan muncul di sini." />
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Pesanan</th><th>Pelanggan</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($recentOrders as $order)
                            <tr class="hover:bg-stone-50/50">
                                <td class="whitespace-nowrap"><p class="font-semibold text-stone-900">{{ $order->code }}</p><p class="text-xs text-stone-500">{{ $order->created_at->diffForHumans() }}</p></td>
                                <td>{{ $order->user->name }}</td>
                                <td class="font-semibold tabular-nums">@rupiah($order->total)</td>
                                <td><x-order-status :status="$order->status" /></td>
                                <td class="text-right"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.admin>
