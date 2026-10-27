@props(['order', 'payment' => null])
{{-- Rincian tagihan (subtotal, ongkir, voucher, biaya layanan, PPN, pembulatan, biaya transaksi, total). Dipakai di dalam <dl>. --}}
@foreach (\App\Support\OrderSummary::rows($order, $payment) as $row)
    <div @class([
        'flex justify-between gap-3',
        'text-emerald-700' => $row['tone'] === 'discount',
        'text-xs text-stone-500' => $row['tone'] === 'info',
        'border-t border-stone-200 pt-2 font-semibold text-stone-900' => $row['tone'] === 'subtotal',
        'border-t border-stone-200 pt-2 text-base font-bold text-stone-900' => $row['tone'] === 'total',
    ])>
        <dt @class(['text-stone-500' => $row['tone'] === 'normal'])>{{ $row['label'] }}@if (! empty($row['note'])) <span class="font-mono text-xs font-semibold">{{ $row['note'] }}</span>@endif</dt>
        <dd class="shrink-0 tabular-nums">{{ \App\Support\OrderSummary::format($row) }}</dd>
    </div>
@endforeach
