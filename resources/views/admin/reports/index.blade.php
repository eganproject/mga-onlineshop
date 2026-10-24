@use('App\Support\Money')
@use('App\Services\Reports\ReportPeriod')
@use('App\Http\Controllers\Admin\ReportController')
@php
    $fmt = fn (int $n) => number_format($n, 0, ',', '.');
    $pct = fn (float $n) => str_replace('.', ',', (string) $n).'%';
    $query = $period->toQuery();
@endphp
<x-layouts.admin title="Laporan">
    <x-admin.page-header title="Laporan penjualan" :description="'Periode '.$period->label().' · dihitung dari pesanan yang sudah dibayar'">
        <div x-data="{ open: false }" class="relative">
            <button type="button" @click="open = !open" @click.outside="open = false" class="btn btn-primary" :aria-expanded="open">
                <x-icon name="download" class="size-4" /> Ekspor CSV <x-icon name="chevron-down" class="size-4" />
            </button>
            <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 z-20 mt-2 w-60 rounded-2xl border border-stone-200 bg-white p-1.5 shadow-xl shadow-stone-900/10">
                @foreach (['penjualan' => 'Rekap penjualan per '.['day' => 'hari', 'week' => 'minggu', 'month' => 'bulan'][$period->group], 'produk' => 'Penjualan per produk', 'kategori' => 'Penjualan per kategori', 'pesanan' => 'Daftar pesanan terjual'] as $type => $label)
                    <a href="{{ route('admin.reports.export', ['type' => $type] + $query) }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-50">
                        <x-icon name="download" class="size-4 text-stone-400" /> {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-admin.page-header>

    {{-- Filter periode --}}
    <form method="GET" action="{{ route('admin.reports.index') }}" x-data="{ preset: @js($period->preset) }"
          class="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="block sm:w-48">
            <span class="mb-1 block text-xs font-semibold text-stone-500">Periode</span>
            <select name="preset" x-model="preset" class="input" x-searchable>
                @foreach (ReportPeriod::PRESETS as $key => $label)
                    <option value="{{ $key }}" @selected($period->preset === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div x-show="preset === 'custom'" @if ($period->preset !== 'custom') x-cloak @endif class="sm:w-72">
            <span class="mb-1 block text-xs font-semibold text-stone-500">Rentang tanggal</span>
            <x-form.date-range from="from" to="to" :from-value="$period->from" :to-value="$period->to" :max="today()" :clearable="false" aria-label="Rentang tanggal" />
        </div>
        @if ($tab === 'penjualan')
            <label class="block sm:w-40">
                <span class="mb-1 block text-xs font-semibold text-stone-500">Kelompokkan</span>
                <select name="group" class="input" x-searchable>
                    @foreach (ReportPeriod::GROUPS as $key => $label)
                        <option value="{{ $key }}" @selected($period->group === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <input type="hidden" name="group" value="{{ $period->group }}">
        @endif
        <button class="btn btn-secondary"><x-icon name="filter" class="size-4" /> Terapkan</button>
    </form>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat-card label="Pendapatan kotor" :value="Money::rupiah($summary['revenue'])" icon="banknotes" :change="$changes['revenue']" />
        <x-admin.stat-card label="Pesanan dibayar" :value="$fmt($summary['orders'])" icon="receipt" tone="sky" :change="$changes['orders']" />
        <x-admin.stat-card label="Rata-rata per pesanan" :value="Money::rupiah($summary['average'])" icon="trending-up" tone="violet" :change="$changes['average']" />
        <x-admin.stat-card label="Produk terjual" :value="$fmt($summary['items']).' pcs'" icon="cube" tone="emerald" :change="$changes['items']" />
    </div>
    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 xl:grid-cols-5">
        @foreach ([
            ['Penjualan produk', Money::rupiah($summary['product_sales'])],
            ['Ongkos kirim', Money::rupiah($summary['shipping'])],
            ['Diskon voucher', $summary['discount'] ? '−'.Money::rupiah($summary['discount']) : Money::rupiah(0)],
            ['Pembeli unik', $fmt($summary['customers']).' orang'],
            ['Pesanan dibatalkan', $fmt($summary['cancelled'])],
        ] as [$label, $value])
            <div class="rounded-2xl border border-stone-200/70 bg-white/60 px-4 py-3">
                <dt class="text-xs text-stone-500">{{ $label }}</dt>
                <dd class="mt-0.5 font-bold tabular-nums text-stone-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    {{-- Tab --}}
    <nav class="mt-8 flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Jenis laporan">
        @foreach (ReportController::TABS as $key => $label)
            <a href="{{ route('admin.reports.index', ['tab' => $key] + $query) }}" @if ($tab === $key) aria-current="page" @endif
               @class(['-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition', 'border-brand-600 text-brand-700' => $tab === $key, 'border-transparent text-stone-500 hover:text-stone-800' => $tab !== $key])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="mt-6">
        @if ($tab === 'penjualan')
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="card min-w-0 p-5 xl:col-span-2">
                    <x-admin.sales-chart :series="$series">
                        <x-slot:title>
                            <h2 class="font-bold text-stone-900">Tren penjualan</h2>
                            <p class="text-xs text-stone-500">{{ ReportPeriod::GROUPS[$period->group] }} · {{ $period->label() }}</p>
                        </x-slot:title>
                    </x-admin.sales-chart>
                </div>
                <div class="card min-w-0 p-5">
                    <h2 class="font-bold text-stone-900">Status pesanan</h2>
                    <p class="text-xs text-stone-500">Pesanan yang dibuat di periode ini</p>
                    @php($statusTotal = max(1, $statuses->sum('count')))
                    <ul class="mt-4 space-y-3">
                        @foreach ($statuses as $row)
                            <li>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <x-order-status :status="$row['status']" />
                                    <span class="font-semibold tabular-nums text-stone-900">{{ $fmt($row['count']) }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 rounded-full bg-stone-100">
                                    <div class="h-1.5 rounded-full bg-stone-400" style="width: {{ $row['count'] ? max(2, round($row['count'] / $statusTotal * 100)) : 0 }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @if ($vouchers->isNotEmpty())
                <div class="card mt-6 overflow-hidden">
                    <div class="p-5">
                        <h2 class="font-bold text-stone-900">Pemakaian voucher</h2>
                        <p class="text-xs text-stone-500">Pesanan terjual yang memakai voucher di periode ini</p>
                    </div>
                    <div class="relative overflow-x-auto">
                        <table class="table-base">
                            <thead class="bg-stone-50/70"><tr><th>Kode voucher</th><th class="text-right">Pesanan</th><th class="text-right">Total diskon</th><th class="text-right">Pendapatan</th></tr></thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($vouchers as $row)
                                    <tr>
                                        <td class="font-mono text-sm font-semibold text-stone-900">{{ $row->voucher_code }}</td>
                                        <td class="text-right tabular-nums">{{ $fmt($row->orders) }}</td>
                                        <td class="text-right tabular-nums text-emerald-700">−@rupiah($row->discount)</td>
                                        <td class="text-right font-semibold tabular-nums">@rupiah($row->revenue)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card mt-6 overflow-hidden">
                <div class="p-5"><h2 class="font-bold text-stone-900">Rincian {{ strtolower(ReportPeriod::GROUPS[$period->group]) }}</h2></div>
                <div class="relative overflow-x-auto">
                    <table class="table-base">
                        <thead class="bg-stone-50/70"><tr><th>Periode</th><th class="text-right">Pesanan</th><th class="text-right">Produk terjual</th><th class="text-right">Pendapatan</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($series->reverse() as $row)
                                <tr @class(['text-stone-400' => $row['orders'] === 0])>
                                    <td class="whitespace-nowrap">{{ $row['label'] }}</td>
                                    <td class="text-right tabular-nums">{{ $fmt($row['orders']) }}</td>
                                    <td class="text-right tabular-nums">{{ $fmt($row['items']) }}</td>
                                    <td class="text-right font-semibold tabular-nums">@rupiah($row['revenue'])</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-stone-200 bg-stone-50/70 font-bold text-stone-900">
                            <tr><td>Total</td><td class="text-right tabular-nums">{{ $fmt($series->sum('orders')) }}</td><td class="text-right tabular-nums">{{ $fmt($series->sum('items')) }}</td><td class="text-right tabular-nums">@rupiah($series->sum('revenue'))</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @else
            @php($rows = $tab === 'produk' ? $products : $categories)
            <div class="card overflow-hidden">
                @if ($rows->isEmpty())
                    <x-empty-state icon="chart" title="Belum ada penjualan" description="Tidak ada pesanan terbayar di periode ini. Coba pilih periode lain." />
                @else
                    <div class="relative overflow-x-auto">
                        <table class="table-base">
                            <thead class="bg-stone-50/70">
                                <tr>
                                    <th class="w-10">#</th>
                                    <th class="min-w-52">{{ $tab === 'produk' ? 'Produk' : 'Kategori' }}</th>
                                    @if ($tab === 'kategori')<th class="text-right">Produk</th>@endif
                                    <th class="text-right">Terjual</th>
                                    <th class="text-right">Pesanan</th>
                                    <th class="text-right">Penjualan</th>
                                    <th class="min-w-40">Kontribusi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($rows as $i => $row)
                                    <tr class="hover:bg-stone-50/50">
                                        <td class="text-stone-400 tabular-nums">{{ $i + 1 }}</td>
                                        <td>
                                            @if ($tab === 'produk')
                                                @if ($row->slug && auth()->user()->can('catalog.manage'))
                                                    <a href="{{ route('admin.products.edit', $row->slug) }}" class="font-semibold text-stone-900 hover:text-brand-700">{{ $row->name }}</a>
                                                @elseif ($row->product_id)
                                                    <span class="font-semibold text-stone-900">{{ $row->name }}</span>
                                                @else
                                                    <span class="font-semibold text-stone-900">{{ $row->name }}</span> <span class="badge bg-stone-100 text-stone-500 ring-stone-500/20">dihapus</span>
                                                @endif
                                                <p class="text-xs text-stone-500">{{ $row->sku ?? 'Tanpa SKU' }}</p>
                                            @else
                                                <span class="font-semibold text-stone-900">{{ $row->name }}</span>
                                            @endif
                                        </td>
                                        @if ($tab === 'kategori')<td class="text-right tabular-nums">{{ $fmt($row->products) }}</td>@endif
                                        <td class="whitespace-nowrap text-right tabular-nums">{{ $fmt($row->quantity) }} pcs</td>
                                        <td class="text-right tabular-nums">{{ $fmt($row->orders) }}</td>
                                        <td class="whitespace-nowrap text-right font-semibold tabular-nums">@rupiah($row->revenue)</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 flex-1 rounded-full bg-stone-100"><div class="h-2 rounded-full bg-brand-500" style="width: {{ max(1, $row->share) }}%"></div></div>
                                                <span class="w-12 text-right text-xs tabular-nums text-stone-500">{{ $pct($row->share) }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-stone-200 bg-stone-50/70 font-bold text-stone-900">
                                <tr>
                                    <td></td><td>Total</td>
                                    @if ($tab === 'kategori')<td></td>@endif
                                    <td class="text-right tabular-nums">{{ $fmt($rows->sum('quantity')) }} pcs</td>
                                    <td></td>
                                    <td class="text-right tabular-nums">@rupiah($rows->sum('revenue'))</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
            <p class="mt-3 text-xs text-stone-400">Penjualan produk dihitung dari subtotal barang (tanpa ongkir). {{ $tab === 'kategori' ? 'Kategori mengikuti kategori produk saat ini.' : '' }}</p>
        @endif
    </div>
</x-layouts.admin>
