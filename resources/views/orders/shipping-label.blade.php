{{-- Label resi untuk ditempel di paket (A6 / kertas label 100 x 150 mm). Variabel: $order, $barcode (SVG atau null). --}}
<x-layouts.print :title="'Label resi '.$order->code">
    <style>
        @page { size: 100mm 150mm; margin: 4mm; }
        .label-barcode svg { width: 100%; height: 100%; }
    </style>
    <article class="print-sheet mx-auto w-full max-w-[100mm] rounded-2xl bg-white p-4 text-[11px] leading-snug shadow-sm print:max-w-none print:p-0">
        <div class="border-2 border-stone-900">
            <header class="flex items-center justify-between gap-3 border-b-2 border-stone-900 px-3 py-2">
                <div class="min-w-0">
                    <p class="truncate text-xl font-extrabold tracking-tight">{{ strtoupper((string) $order->courier) }}</p>
                    <p class="font-semibold uppercase">{{ $order->courier_service }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="font-bold">{{ config('shop.name') }}</p>
                    <p class="font-mono">{{ $order->code }}</p>
                </div>
            </header>

            <section class="border-b-2 border-stone-900 px-3 py-3 text-center">
                @if ($barcode)
                    <div class="label-barcode mx-auto h-16 w-full max-w-[80mm]">{!! $barcode !!}</div>
                @endif
                <p class="mt-1 font-mono text-base font-bold tracking-wider break-all">{{ $order->tracking_number }}</p>
            </section>

            <section class="border-b border-stone-900 px-3 py-2">
                <p class="text-[9px] font-bold tracking-wider uppercase">Penerima</p>
                <p class="text-base font-extrabold">{{ $order->recipient_name }}</p>
                <p class="font-semibold">{{ $order->recipient_phone }}</p>
                <p class="mt-1 text-xs">{{ $order->shipping_address }}</p>
            </section>

            <section class="border-b border-stone-900 px-3 py-2">
                <p class="text-[9px] font-bold tracking-wider uppercase">Pengirim</p>
                <p class="font-bold">{{ config('shop.full_name') }}@if (config('shop.whatsapp')) · {{ config('shop.whatsapp') }}@endif</p>
                @if (config('shop.address'))<p>{{ config('shop.address') }}</p>@endif
            </section>

            <section class="grid grid-cols-3 divide-x divide-stone-900 border-b border-stone-900 text-center">
                <div class="px-2 py-1.5"><p class="text-[9px] font-bold uppercase">Berat</p><p class="font-bold">{{ number_format($order->total_weight / 1000, 2, ',', '.') }} kg</p></div>
                <div class="px-2 py-1.5"><p class="text-[9px] font-bold uppercase">Jumlah</p><p class="font-bold">{{ $order->items->sum('quantity') }} barang</p></div>
                <div class="px-2 py-1.5"><p class="text-[9px] font-bold uppercase">Tanggal</p><p class="font-bold">{{ ($order->shipped_at ?? now())->translatedFormat('d M Y') }}</p></div>
            </section>

            <section class="px-3 py-2">
                <p class="text-[9px] font-bold tracking-wider uppercase">Isi paket</p>
                <ul>
                    @foreach ($order->items->take(6) as $item)
                        <li class="line-clamp-1">{{ $item->quantity }}x {{ $item->product_name }}@if ($item->variant_name) ({{ $item->variant_name }})@endif</li>
                    @endforeach
                    @if ($order->items->count() > 6)<li>+ {{ $order->items->count() - 6 }} produk lain</li>@endif
                </ul>
                @if ($order->notes)<p class="mt-1"><span class="font-bold">Catatan:</span> {{ \Illuminate\Support\Str::limit($order->notes, 120) }}</p>@endif
            </section>
        </div>
    </article>
</x-layouts.print>
