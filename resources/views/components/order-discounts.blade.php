@props(['order'])
{{-- Baris potongan voucher di ringkasan pesanan. --}}
@if ($order->discount > 0)
    <div class="flex justify-between gap-3 text-emerald-700"><dt>Diskon voucher @if ($order->voucher_code)<span class="font-mono text-xs font-semibold">{{ $order->voucher_code }}</span>@endif</dt><dd class="shrink-0">−@rupiah($order->discount)</dd></div>
@endif
@if ($order->shipping_discount > 0)
    <div class="flex justify-between gap-3 text-emerald-700"><dt>Potongan ongkir @if ($order->voucher_code && ! $order->discount)<span class="font-mono text-xs font-semibold">{{ $order->voucher_code }}</span>@endif</dt><dd class="shrink-0">−@rupiah($order->shipping_discount)</dd></div>
@endif
