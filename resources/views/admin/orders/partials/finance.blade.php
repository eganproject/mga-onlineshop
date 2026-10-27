{{-- Invoice, data tagihan perusahaan, dan rincian dana (biaya Midtrans, bersih diterima). --}}
<div class="card p-5 text-sm">
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-bold text-stone-900">Invoice & keuangan</h2>
        @if ($order->invoice_number)
            <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Terbit</span>
        @else
            <span class="badge bg-stone-100 text-stone-600 ring-stone-500/20">Belum terbit</span>
        @endif
    </div>
    <dl class="mt-3 space-y-2">
        <div class="flex justify-between gap-3">
            <dt class="text-stone-500">No. invoice</dt>
            <dd class="text-right font-mono text-xs font-semibold text-stone-900">{{ $order->invoice_number ?? '—' }}</dd>
        </div>
        @if ($order->invoice_issued_at)
            <div class="flex justify-between gap-3"><dt class="text-stone-500">Tanggal invoice</dt><dd>{{ $order->invoice_issued_at->translatedFormat('d M Y') }}</dd></div>
        @endif
        @if ($order->billing_name)
            <div class="border-t border-stone-100 pt-2">
                <dt class="text-xs text-stone-500">Ditagihkan ke perusahaan</dt>
                <dd class="mt-0.5 font-semibold text-stone-900">{{ $order->billing_name }}</dd>
                @if ($order->billing_npwp)<dd class="font-mono text-xs text-stone-600">NPWP {{ \App\Services\Billing\BillingSettings::formatNpwp($order->billing_npwp) }}</dd>@endif
                @if ($order->billing_address)<dd class="text-xs text-stone-600">{{ $order->billing_address }}</dd>@endif
                @if ($order->billing_email)<dd class="text-xs text-stone-600">{{ $order->billing_email }}</dd>@endif
            </div>
        @endif
    </dl>

    @can('reports.view')
        @if ($order->paid_at)
            <dl class="mt-3 space-y-2 border-t border-stone-100 pt-3">
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Metode bayar</dt><dd class="text-right">{{ $order->paymentMethodLabel() ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Dibayar pelanggan</dt><dd class="font-semibold tabular-nums">@rupiah($order->amountPaid())</dd></div>
                @if ($order->payment_fee)
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Biaya transaksi dari pembeli</dt><dd class="tabular-nums">@rupiah($order->payment_fee)</dd></div>
                @endif
                <div class="flex justify-between gap-3">
                    <dt class="flex items-center gap-1 text-stone-500">Potongan Midtrans
                        <x-tooltip label="Potongan Midtrans" text="Perkiraan dari tarif per metode di Admin › Pajak & Biaya saat pembayaran diterima. Angka pasti ada di laporan settlement Midtrans." />
                    </dt>
                    <dd class="tabular-nums text-rose-600">{{ $order->gateway_fee ? '−'.\App\Support\Money::rupiah($order->gateway_fee) : 'Rp0' }}</dd>
                </div>
                <div class="flex justify-between gap-3 border-t border-stone-100 pt-2 font-bold text-stone-900"><dt>Bersih diterima</dt><dd class="tabular-nums">@rupiah($order->netReceived())</dd></div>
                @if ($order->hasTax())
                    <div class="flex justify-between gap-3 text-xs text-stone-500"><dt>{{ $order->taxLabel() }} yang harus disetor</dt><dd class="tabular-nums">@rupiah($order->tax_amount)</dd></div>
                @endif
            </dl>
        @elseif ($order->hasTax() || $order->service_fee)
            <p class="mt-3 border-t border-stone-100 pt-3 text-xs text-stone-500">Rincian dana bersih tampil setelah pesanan dibayar.</p>
        @endif
    @endcan
</div>
