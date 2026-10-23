<x-layouts.print :title="$orders->count() === 1 ? 'Packing slip '.$orders->first()->code : 'Packing slip ('.$orders->count().' pesanan)'">
    @foreach ($orders as $order)
        <article class="print-sheet break-inside-avoid rounded-2xl bg-white p-6 shadow-sm sm:p-8 print:break-after-page print:last:break-after-auto">
            {{-- Label alamat: bisa digunting dan ditempel di paket. --}}
            <section class="rounded-xl border-2 border-dashed border-stone-300 p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-wider text-stone-400 uppercase">Penerima</p>
                        <p class="mt-1 text-lg font-extrabold">{{ $order->recipient_name }}</p>
                        <p class="font-semibold">{{ $order->recipient_phone }}</p>
                        <p class="mt-1 max-w-md text-sm">{{ $order->shipping_address }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-extrabold tracking-tight">{{ strtoupper($order->courier ?? '—') }}</p>
                        <p class="text-sm font-semibold">{{ $order->courier_service }}</p>
                        @if ($order->tracking_number)<p class="mt-1 font-mono text-sm">{{ $order->tracking_number }}</p>@endif
                        <p class="mt-1 text-xs text-stone-500">{{ number_format($order->total_weight / 1000, 2, ',', '.') }} kg</p>
                    </div>
                </div>
                <div class="mt-4 border-t border-stone-200 pt-3 text-xs text-stone-600">
                    <span class="font-bold text-stone-400 uppercase">Pengirim:</span>
                    {{ config('shop.full_name') }}@if (config('shop.whatsapp')) · {{ config('shop.whatsapp') }}@endif @if (config('shop.address')) · {{ config('shop.address') }}@endif
                </div>
            </section>

            <section class="mt-6">
                <div class="flex items-baseline justify-between">
                    <h2 class="font-bold">Isi paket · <span class="font-mono">{{ $order->code }}</span></h2>
                    <p class="text-xs text-stone-500">{{ $order->created_at->translatedFormat('d M Y, H:i') }}</p>
                </div>
                <table class="mt-3 w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs tracking-wider text-stone-400 uppercase">
                            <th class="w-8 py-2"></th>
                            <th class="py-2 font-bold">Produk</th>
                            <th class="py-2 font-bold">SKU</th>
                            <th class="py-2 text-right font-bold">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="py-2"><span class="inline-block size-4 rounded border border-stone-400"></span></td>
                                <td class="py-2"><p class="font-semibold">{{ $item->product_name }}</p>@if ($item->variant_name)<p class="text-xs text-stone-500">{{ $item->variant_name }}</p>@endif</td>
                                <td class="py-2 font-mono text-xs">{{ $item->sku ?? '—' }}</td>
                                <td class="py-2 text-right text-base font-bold">{{ $item->quantity }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-2 text-right text-xs text-stone-500">Total {{ $order->items->sum('quantity') }} barang</p>
                @if ($order->notes)
                    <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><span class="font-bold">Catatan pembeli:</span> {{ $order->notes }}</p>
                @endif
            </section>
        </article>
    @endforeach
</x-layouts.print>
