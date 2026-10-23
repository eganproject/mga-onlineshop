@php($payment = $order->latestPayment)
<x-layouts.print :title="'Invoice '.$order->code">
    <article class="print-sheet rounded-2xl bg-white p-6 shadow-sm sm:p-10">
        <header class="flex flex-wrap items-start justify-between gap-6 border-b border-stone-200 pb-6">
            <div>
                <x-logo class="h-12" />
                @if (config('shop.address'))<p class="mt-3 max-w-xs text-xs text-stone-500">{{ config('shop.address') }}</p>@endif
                @if (config('shop.whatsapp'))<p class="text-xs text-stone-500">WA {{ config('shop.whatsapp') }}</p>@endif
            </div>
            <div class="text-right">
                <h1 class="text-2xl font-extrabold tracking-tight">INVOICE</h1>
                <p class="mt-1 font-mono text-sm font-semibold">{{ $order->code }}</p>
                <p class="text-xs text-stone-500">Tanggal pesan: {{ $order->created_at->translatedFormat('d F Y') }}</p>
                @if ($order->paid_at)<p class="text-xs text-stone-500">Tanggal bayar: {{ $order->paid_at->translatedFormat('d F Y') }}</p>@endif
                <span @class([
                    'mt-2 inline-block rounded-full px-3 py-1 text-xs font-bold',
                    'bg-emerald-50 text-emerald-700' => $order->paid_at && $order->status !== \App\Enums\OrderStatus::Cancelled,
                    'bg-rose-50 text-rose-700' => $order->status === \App\Enums\OrderStatus::Cancelled,
                    'bg-amber-50 text-amber-700' => ! $order->paid_at && $order->status !== \App\Enums\OrderStatus::Cancelled,
                ])>
                    {{ $order->status === \App\Enums\OrderStatus::Cancelled ? 'DIBATALKAN' : ($order->paid_at ? 'LUNAS' : 'BELUM DIBAYAR') }}
                </span>
            </div>
        </header>

        <section class="grid gap-6 border-b border-stone-200 py-6 text-sm sm:grid-cols-2">
            <div>
                <p class="text-xs font-bold tracking-wider text-stone-400 uppercase">Ditagihkan kepada</p>
                <p class="mt-1 font-semibold">{{ $order->recipient_name }}</p>
                <p class="text-stone-600">{{ $order->recipient_phone }}</p>
                <p class="text-stone-600">{{ $order->shipping_address }}</p>
            </div>
            <div class="sm:text-right">
                <p class="text-xs font-bold tracking-wider text-stone-400 uppercase">Pengiriman</p>
                <p class="mt-1 font-semibold">{{ $order->courierLabel() ?? '—' }}</p>
                @if ($order->tracking_number)<p class="font-mono text-stone-600">Resi {{ $order->tracking_number }}</p>@endif
                @if ($payment?->payment_type)<p class="mt-2 text-stone-600">Metode bayar: {{ ucwords(str_replace('_', ' ', $payment->payment_type)) }}</p>@endif
            </div>
        </section>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-stone-200 text-left text-xs tracking-wider text-stone-400 uppercase">
                    <th class="py-3 font-bold">Produk</th>
                    <th class="py-3 text-center font-bold">Qty</th>
                    <th class="py-3 text-right font-bold">Harga</th>
                    <th class="py-3 text-right font-bold">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($order->items as $item)
                    <tr>
                        <td class="py-3">
                            <p class="font-semibold">{{ $item->product_name }}</p>
                            <p class="text-xs text-stone-500">{{ collect([$item->variant_name, $item->sku])->filter()->implode(' · ') }}</p>
                        </td>
                        <td class="py-3 text-center">{{ $item->quantity }}</td>
                        <td class="py-3 text-right whitespace-nowrap">@rupiah($item->price)</td>
                        <td class="py-3 text-right font-semibold whitespace-nowrap">@rupiah($item->subtotal)</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <dl class="ml-auto mt-4 max-w-xs space-y-2 border-t border-stone-200 pt-4 text-sm">
            <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd>@rupiah($order->subtotal)</dd></div>
            <div class="flex justify-between"><dt class="text-stone-500">Ongkir ({{ number_format($order->total_weight / 1000, 2, ',', '.') }} kg)</dt><dd>@rupiah($order->shipping_cost)</dd></div>
            <x-order-discounts :order="$order" />
            <div class="flex justify-between border-t border-stone-200 pt-2 text-base font-extrabold"><dt>Total</dt><dd>@rupiah($order->total)</dd></div>
        </dl>

        <footer class="mt-10 border-t border-stone-200 pt-4 text-center text-xs text-stone-400">
            Terima kasih telah berbelanja di {{ config('shop.full_name') }}. Invoice ini sah tanpa tanda tangan.
        </footer>
    </article>
</x-layouts.print>
