<x-layouts.admin title="Dashboard">
    <div class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-stone-500">{{ now()->translatedFormat('l, d F Y') }}</p>
            <h1 class="text-2xl font-extrabold tracking-tight text-stone-900">Halo, {{ \Illuminate\Support\Str::words(auth()->user()->name, 1, '') }}! ☕</h1>
        </div>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary mt-3 self-start sm:mt-0"><x-icon name="plus" class="size-4" /> Tambah produk</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card label="Pendapatan bulan ini" :value="\App\Support\Money::rupiah($stats['revenue'])" icon="banknotes">
            <x-slot:hint>
                @if (! is_null($stats['revenue_change']))
                    <span @class(['font-semibold', 'text-emerald-600' => $stats['revenue_change'] >= 0, 'text-rose-600' => $stats['revenue_change'] < 0])>
                        {{ $stats['revenue_change'] >= 0 ? '▲' : '▼' }} {{ abs($stats['revenue_change']) }}%
                    </span> dibanding bulan lalu
                @else
                    Belum ada data bulan lalu
                @endif
            </x-slot:hint>
        </x-admin.stat-card>
        <x-admin.stat-card label="Pesanan hari ini" :value="$stats['orders_today']" icon="receipt" tone="sky" hint="Semua status" />
        <x-admin.stat-card label="Perlu diproses" :value="$stats['to_process']" icon="truck" tone="violet" hint="Sudah dibayar, belum dikirim" />
        <x-admin.stat-card label="Pelanggan" :value="number_format($stats['customers'], 0, ',', '.')" icon="users" tone="emerald" :hint="'+'.$stats['new_customers'].' bulan ini'" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Grafik pendapatan --}}
        <div class="card min-w-0 overflow-hidden p-5 xl:col-span-2">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-stone-900">Pendapatan 7 hari terakhir</h2>
                    <p class="text-xs text-stone-500">Dari pesanan yang sudah dibayar</p>
                </div>
                <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">@rupiah($chart->sum('value'))</span>
            </div>
            <div class="mt-6 flex h-56 items-end gap-2 sm:gap-4">
                @foreach ($chart as $day)
                    @php($height = $day['value'] > 0 ? max(4, round($day['value'] / $chartMax * 100)) : 2)
                    <div class="group flex flex-1 flex-col items-center gap-2">
                        <div class="relative flex h-44 w-full items-end">
                            <div class="pointer-events-none absolute -top-2 left-1/2 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-stone-900 px-2 py-1 text-[11px] font-semibold text-white opacity-0 transition group-hover:opacity-100">
                                @rupiah($day['value'])
                            </div>
                            <div class="w-full rounded-t-lg bg-gradient-to-t from-brand-500 to-brand-300 transition group-hover:from-brand-600 group-hover:to-brand-400"
                                 style="height: {{ $height }}%"></div>
                        </div>
                        <div class="text-center">
                            <p class="text-xs font-semibold text-stone-700">{{ $day['label'] }}</p>
                            <p class="hidden text-[10px] text-stone-400 sm:block">{{ $day['date'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Stok menipis --}}
        <div class="card min-w-0 p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-stone-900">Stok menipis</h2>
                <a href="{{ route('admin.products.index', ['status' => 'stok-menipis']) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Lihat semua</a>
            </div>
            <ul class="mt-4 space-y-3">
                @forelse ($lowStock as $product)
                    <li class="flex items-center gap-3">
                        <x-product-image :product="$product" class="size-11 shrink-0 rounded-xl" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('admin.products.edit', $product) }}" class="block truncate text-sm font-semibold text-stone-800 hover:text-brand-700">{{ $product->name }}</a>
                            <p class="text-xs text-stone-500">{{ $product->sku ?? 'Tanpa SKU' }}</p>
                        </div>
                        <span @class(['badge', 'bg-rose-50 text-rose-700 ring-rose-600/20' => $product->stock === 0, 'bg-amber-50 text-amber-700 ring-amber-600/20' => $product->stock > 0])>
                            {{ $product->stock === 0 ? 'Habis' : $product->stock.' pcs' }}
                        </span>
                    </li>
                @empty
                    <li class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">Semua stok aman 👍</li>
                @endforelse
            </ul>
        </div>
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
                                <td><p class="font-semibold text-stone-900">{{ $order->code }}</p><p class="text-xs text-stone-500">{{ $order->created_at->diffForHumans() }}</p></td>
                                <td>{{ $order->user->name }}</td>
                                <td class="font-semibold">@rupiah($order->total)</td>
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
