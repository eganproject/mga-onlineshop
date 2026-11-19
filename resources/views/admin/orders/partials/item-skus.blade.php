{{-- Ringkasan item pesanan di daftar: SKU × jumlah saja (tanpa nama produk). --}}
@php
    $limit = 3;
    $lines = $order->items;
    $all = $lines->map(fn ($item) => ($item->sku ?: 'Tanpa SKU').' × '.$item->quantity)->implode(', ');
@endphp
<ul class="min-w-36 max-w-56 space-y-0.5 whitespace-normal text-xs" title="{{ $all }}">
    @foreach ($lines->take($limit) as $item)
        <li>
            @if ($item->sku)
                <span class="break-words font-mono text-stone-700">{{ $item->sku }}</span>
            @else
                <span class="italic text-stone-400">Tanpa SKU</span>
            @endif
            <span class="whitespace-nowrap font-semibold text-stone-900">× {{ $item->quantity }}</span>
        </li>
    @endforeach
    @if ($lines->count() > $limit)
        <li class="text-stone-500">+{{ $lines->count() - $limit }} SKU lainnya</li>
    @endif
</ul>
