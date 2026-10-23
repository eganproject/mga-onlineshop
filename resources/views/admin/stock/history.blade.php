<x-layouts.admin title="Riwayat Stok">
    <x-admin.page-header title="Riwayat stok" :description="$product ? 'Pergerakan stok '.$product->name : 'Semua pergerakan stok: barang masuk, penjualan, pembatalan, dan penyesuaian.'" :back="route('admin.stock.index')">
        @if ($product)
            <a href="{{ route('admin.stock.history', request()->except('produk', 'page')) }}" class="btn btn-secondary">Tampilkan semua produk</a>
        @endif
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4 sm:flex-row sm:flex-wrap" x-data @change="$el.submit()">
            @if ($product)<input type="hidden" name="produk" value="{{ $product->id }}">@endif
            @unless ($product)
                <div class="relative flex-1 sm:max-w-xs">
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Produk, SKU, varian, catatan..." class="input pl-10" aria-label="Cari riwayat">
                </div>
            @endunless
            <x-form.date-range from="from" to="to" :from-value="request('from')" :to-value="request('to')" :max="today()" placeholder="Semua tanggal" wrapper-class="sm:w-64" aria-label="Rentang tanggal" />
            <select name="jenis" class="input sm:w-52" x-searchable aria-label="Filter jenis">
                <option value="">Semua jenis</option>
                @foreach (\App\Enums\StockMovementType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </form>

        @if ($movements->isEmpty())
            @if (request()->hasAny(['q', 'from', 'to', 'jenis']))
                <x-empty-state icon="clock" title="Tidak ada riwayat yang cocok" description="Ubah kata kunci, tanggal, atau jenis pergerakan.">
                    <a href="{{ route('admin.stock.history', array_filter(['produk' => $product?->id])) }}" class="btn btn-secondary">Hapus filter</a>
                </x-empty-state>
            @else
                <x-empty-state icon="clock" title="Belum ada riwayat" description="Perubahan stok akan tercatat di sini." />
            @endif
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Waktu</th><th>Produk</th><th>Jenis</th><th class="text-right">Perubahan</th><th class="text-right">Stok akhir</th><th>Keterangan</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($movements as $movement)
                            <tr>
                                <td class="whitespace-nowrap text-xs text-stone-500">{{ $movement->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    @if ($movement->product)
                                        <a href="{{ route('admin.stock.history', ['produk' => $movement->product_id] + request()->except('produk', 'page', 'q')) }}" class="font-semibold text-stone-900 hover:text-brand-700">{{ $movement->product->name }}</a>
                                    @else
                                        <span class="italic text-stone-400">Produk sudah dihapus</span>
                                    @endif
                                    @if ($movement->variant_name)<p class="text-xs text-stone-500">{{ $movement->variant_name }}</p>@endif
                                </td>
                                <td><span class="badge {{ $movement->type->badgeClass() }}">{{ $movement->type->label() }}</span></td>
                                <td @class(['whitespace-nowrap text-right font-bold', 'text-emerald-600' => $movement->quantity > 0, 'text-rose-600' => $movement->quantity < 0])>
                                    {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                                </td>
                                <td class="text-right font-semibold text-stone-900">{{ $movement->stock_after }}</td>
                                <td class="text-xs text-stone-500">
                                    @if ($movement->order)
                                        <a href="{{ route('admin.orders.show', $movement->order) }}" class="font-semibold text-brand-700 hover:underline">{{ $movement->order->code }}</a>
                                    @endif
                                    {{ $movement->note }}
                                    @if ($movement->user)<span class="block text-stone-400">oleh {{ $movement->user->name }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$movements" label="riwayat" />
        @endif
    </div>
</x-layouts.admin>
