{{-- Tab "Keuangan & pajak": dari pendapatan kotor sampai dana bersih, PPN, dan rincian per metode bayar. --}}
@php
    $taxIncluded = $summary['tax_included'];
    $lines = [
        ['Penjualan produk', $summary['product_sales'], 'normal'],
        ['Diskon voucher', -$summary['discount'], 'discount'],
        ['Ongkos kirim', $summary['shipping'], 'normal'],
        ['Biaya layanan', $summary['service_fee'], 'normal'],
        ['PPN ditambahkan ke tagihan', $summary['tax'] - $taxIncluded, 'normal'],
        ['Pembulatan', $summary['rounding'], 'normal'],
        ['Total pesanan (pendapatan kotor)', $summary['revenue'], 'subtotal'],
        ['Biaya transaksi dibayar pembeli', $summary['payment_fee'], 'normal'],
        ['Dibayar pelanggan', $summary['paid'], 'subtotal'],
        ['Potongan Midtrans (perkiraan)', -$summary['gateway_fee'], 'cost'],
        ['Dana bersih diterima', $summary['net'], 'total'],
    ];
@endphp
<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="card min-w-0 p-5 xl:col-span-2">
        <h2 class="font-bold text-stone-900">Rincian keuangan</h2>
        <p class="text-xs text-stone-500">Pesanan dibayar di periode {{ $period->label() }}. Potongan Midtrans dihitung dari tarif di Pajak & Biaya; angka pasti ada di laporan settlement Midtrans.</p>
        <dl class="mt-4 space-y-2 text-sm">
            @foreach ($lines as [$label, $amount, $tone])
                @continue($amount === 0 && $tone === 'normal' && $label !== 'Ongkos kirim')
                <div @class([
                    'flex justify-between gap-3',
                    'text-emerald-700' => $tone === 'discount',
                    'text-rose-600' => $tone === 'cost',
                    'border-t border-stone-200 pt-2 font-semibold text-stone-900' => $tone === 'subtotal',
                    'border-t-2 border-stone-900 pt-2 text-base font-bold text-stone-900' => $tone === 'total',
                ])>
                    <dt @class(['text-stone-500' => $tone === 'normal'])>{{ $label }}</dt>
                    <dd class="tabular-nums">{{ $amount < 0 ? '−' : '' }}@rupiah(abs($amount))</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="card min-w-0 space-y-3 p-5">
        <h2 class="font-bold text-stone-900">Pajak (PPN)</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-stone-500">PPN dipungut</dt><dd class="font-bold tabular-nums">@rupiah($summary['tax'])</dd></div>
            @if ($taxIncluded)
                <div class="flex justify-between gap-3 text-xs text-stone-500"><dt>Dari harga yang sudah termasuk PPN</dt><dd class="tabular-nums">@rupiah($taxIncluded)</dd></div>
            @endif
            <div class="flex justify-between gap-3 border-t border-stone-100 pt-2"><dt class="text-stone-500">Dana bersih setelah PPN</dt><dd class="font-semibold tabular-nums">@rupiah($summary['net_after_tax'])</dd></div>
        </dl>
        <p class="text-xs text-stone-500">PPN yang dipungut perlu disetor & dilaporkan (SPT Masa PPN / e-Faktur di Coretax). Unduh <a class="font-semibold text-brand-700 underline" href="{{ route('admin.reports.export', ['type' => 'pajak'] + $period->toQuery()) }}">rekap PPN per invoice</a> untuk konsultan pajak.</p>
    </div>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="flex flex-wrap items-end justify-between gap-2 p-5">
        <div>
            <h2 class="font-bold text-stone-900">Per metode bayar</h2>
            <p class="text-xs text-stone-500">Siapa menanggung biaya, berapa dipotong Midtrans, dan dana bersih per metode.</p>
        </div>
        <a href="{{ route('admin.reports.export', ['type' => 'metode'] + $period->toQuery()) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4" /> CSV</a>
    </div>
    @if ($methods->isEmpty())
        <p class="px-5 pb-5 text-sm text-stone-500">Belum ada pesanan dibayar di periode ini.</p>
    @else
        <div class="relative overflow-x-auto">
            <table class="table-base">
                <thead class="bg-stone-50/70"><tr><th>Metode</th><th class="text-right">Pesanan</th><th class="text-right">Dibayar pelanggan</th><th class="hidden text-right sm:table-cell">Biaya dari pembeli</th><th class="text-right">Potongan Midtrans</th><th class="text-right">Bersih</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($methods as $row)
                        <tr>
                            <td class="font-semibold text-stone-900">{{ $row->label }}<p class="text-xs font-normal text-stone-500">{{ str_replace('.', ',', (string) $row->share) }}% dari dana masuk</p></td>
                            <td class="text-right tabular-nums">{{ number_format($row->orders, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap text-right tabular-nums">@rupiah($row->paid)</td>
                            <td class="hidden whitespace-nowrap text-right tabular-nums sm:table-cell">@rupiah($row->payment_fee)</td>
                            <td class="whitespace-nowrap text-right tabular-nums text-rose-600">−@rupiah($row->gateway_fee)</td>
                            <td class="whitespace-nowrap text-right font-semibold tabular-nums">@rupiah($row->net)</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-stone-200 bg-stone-50/70 font-bold text-stone-900">
                    <tr>
                        <td>Total</td>
                        <td class="text-right tabular-nums">{{ number_format($methods->sum('orders'), 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap text-right tabular-nums">@rupiah($methods->sum('paid'))</td>
                        <td class="hidden whitespace-nowrap text-right tabular-nums sm:table-cell">@rupiah($methods->sum('payment_fee'))</td>
                        <td class="whitespace-nowrap text-right tabular-nums">−@rupiah($methods->sum('gateway_fee'))</td>
                        <td class="whitespace-nowrap text-right tabular-nums">@rupiah($methods->sum('net'))</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
