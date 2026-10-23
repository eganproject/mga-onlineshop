<x-layouts.admin title="Riwayat Stok">
    <x-admin.page-header title="Riwayat stok" :description="$product ? 'Pergerakan stok '.$product->name : 'Semua pergerakan stok: barang masuk, penjualan, pembatalan, dan penyesuaian.'" :back="route('admin.stock.index')">
        @if ($product)
            <a href="{{ route('admin.stock.history', request()->except('produk', 'page')) }}" class="btn btn-secondary">Tampilkan semua produk</a>
        @endif
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-3 border-b border-stone-100 p-4" x-data @change="$el.submit()">
            @if ($product)<input type="hidden" name="produk" value="{{ $product->id }}">@endif
            <select name="jenis" class="input sm:w-52">
                <option value="">Semua jenis</option>
                @foreach (\App\Enums\StockMovementType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </form>

        @if ($movements->isEmpty())
            <x-empty-state icon="clock" title="Belum ada riwayat" description="Perubahan stok akan tercatat di sini." />
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
                                        <a href="{{ route('admin.stock.history', ['produk' => $movement->product_id] + request()->except('produk', 'page')) }}" class="font-semibold text-stone-900 hover:text-brand-700">{{ $movement->product->name }}</a>
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
            <div class="border-t border-stone-100 p-4">{{ $movements->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
