{{-- Invoice / tagihan siap cetak. Data dari App\Services\Billing\InvoiceRenderer (sama dengan versi PDF). --}}
@php
    $statusClass = ['emerald' => 'bg-emerald-50 text-emerald-700', 'rose' => 'bg-rose-50 text-rose-700', 'amber' => 'bg-amber-50 text-amber-700'][$status[1]];
@endphp
<x-layouts.print :title="($isInvoice ? 'Invoice ' : 'Tagihan ').$number">
    @isset($pdfUrl)
        <x-slot:actions>
            <a href="{{ $pdfUrl }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4" /> PDF</a>
        </x-slot:actions>
    @endisset

    <article class="print-sheet rounded-2xl bg-white p-6 shadow-sm sm:p-10">
        <header class="flex flex-wrap items-start justify-between gap-6 border-b border-stone-200 pb-6">
            <div class="max-w-xs">
                <x-logo class="h-12" />
                <p class="mt-3 text-sm font-bold text-stone-900">{{ $company['name'] }}</p>
                @if ($company['npwp'])<p class="text-xs text-stone-600">NPWP {{ \App\Services\Billing\BillingSettings::formatNpwp($company['npwp']) }}@if ($company['is_pkp']) · PKP @endif</p>@endif
                @if ($company['address'])<p class="mt-1 text-xs text-stone-500">{{ $company['address'] }}</p>@endif
                <p class="text-xs text-stone-500">{{ collect([$company['phone'], $company['email']])->filter()->implode(' · ') }}</p>
            </div>
            <div class="text-right">
                <h1 class="text-2xl font-extrabold tracking-tight">{{ $title }}</h1>
                <p class="mt-1 font-mono text-sm font-semibold">{{ $number }}</p>
                @if ($isInvoice)<p class="text-xs text-stone-500">Tanggal invoice: {{ $order->invoice_issued_at->translatedFormat('d F Y') }}</p>@endif
                <p class="text-xs text-stone-500">No. pesanan: <span class="font-mono">{{ $order->code }}</span></p>
                <p class="text-xs text-stone-500">Tanggal pesan: {{ $order->created_at->translatedFormat('d F Y') }}</p>
                @if ($order->paid_at)
                    <p class="text-xs text-stone-500">Tanggal bayar: {{ $order->paid_at->translatedFormat('d F Y') }}</p>
                @elseif ($status[0] === 'BELUM DIBAYAR')
                    <p class="text-xs text-stone-500">Bayar sebelum: {{ $order->paymentDeadline()->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i') }} WIB</p>
                @endif
                <span class="mt-2 inline-block rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">{{ $status[0] }}</span>
            </div>
        </header>

        <section class="grid grid-cols-1 gap-6 border-b border-stone-200 py-6 text-sm sm:grid-cols-2">
            <div>
                <p class="text-xs font-bold tracking-wider text-stone-400 uppercase">Ditagihkan kepada</p>
                <p class="mt-1 font-semibold">{{ $order->billedName() }}</p>
                @if ($order->billing_npwp)<p class="text-stone-600">NPWP {{ \App\Services\Billing\BillingSettings::formatNpwp($order->billing_npwp) }}</p>@endif
                @if ($order->billing_name)
                    <p class="text-stone-600">{{ $order->billing_address ?: $order->shipping_address }}</p>
                    <p class="text-stone-600">{{ $order->billing_email ?: $order->user?->email }}</p>
                    <p class="text-stone-600">u.p. {{ $order->recipient_name }} · {{ $order->recipient_phone }}</p>
                @else
                    <p class="text-stone-600">{{ $order->recipient_phone }}@if ($order->user?->email) · {{ $order->user->email }}@endif</p>
                    <p class="text-stone-600">{{ $order->shipping_address }}</p>
                @endif
            </div>
            <div class="sm:text-right">
                <p class="text-xs font-bold tracking-wider text-stone-400 uppercase">Pengiriman & pembayaran</p>
                <p class="mt-1 font-semibold">{{ $order->courierLabel() ?? '—' }}</p>
                @if ($order->billing_name)<p class="text-stone-600">Dikirim ke {{ $order->recipient_name }}</p>@endif
                @if ($order->tracking_number)<p class="font-mono text-stone-600">Resi {{ $order->tracking_number }}</p>@endif
                @php $methodLabel = $order->paid_at ? $order->paymentMethodLabel() : $payment?->methodLabel(); @endphp
                @if ($methodLabel)<p class="mt-2 text-stone-600">Metode bayar: {{ $methodLabel }}</p>@endif
            </div>
        </section>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-stone-200 text-left text-xs tracking-wider text-stone-400 uppercase">
                        <th class="py-3 pr-2 font-bold">No</th>
                        <th class="py-3 font-bold">Produk</th>
                        <th class="py-3 text-center font-bold">Qty</th>
                        <th class="py-3 text-right font-bold">Harga</th>
                        <th class="py-3 text-right font-bold">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="py-3 pr-2 align-top text-stone-500">{{ $loop->iteration }}</td>
                            <td class="py-3">
                                <p class="font-semibold">{{ $item->product_name }}</p>
                                <p class="text-xs text-stone-500">{{ collect([$item->variant_name, $item->sku])->filter()->implode(' · ') }}</p>
                            </td>
                            <td class="py-3 text-center">{{ $item->quantity }}</td>
                            <td class="py-3 pl-3 text-right whitespace-nowrap">@rupiah($item->price)</td>
                            <td class="py-3 pl-3 text-right font-semibold whitespace-nowrap">@rupiah($item->subtotal)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-col-reverse gap-6 border-t border-stone-200 pt-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-sm space-y-3 text-xs text-stone-500">
                @if ($order->hasTax())
                    <div class="rounded-xl bg-stone-50 p-3">
                        <p class="font-bold text-stone-700">Rincian pajak</p>
                        <p class="mt-1 flex justify-between gap-4"><span>Dasar Pengenaan Pajak{{ $order->tax_dpp_other ? ' (nilai lain 11/12)' : '' }}</span><span class="tabular-nums">@rupiah($order->tax_base)</span></p>
                        <p class="flex justify-between gap-4"><span>{{ $order->tax_label ?: 'PPN' }} {{ \App\Services\Billing\BillingSettings::percent($order->tax_rate) }}</span><span class="tabular-nums">@rupiah($order->tax_amount)</span></p>
                        <p class="mt-1">{{ $order->tax_inclusive ? 'Harga sudah termasuk pajak.' : 'Pajak ditambahkan ke tagihan.' }}</p>
                    </div>
                @endif
                @if ($notes)<p class="whitespace-pre-line">{{ $notes }}</p>@endif
            </div>
            <dl class="w-full space-y-2 text-sm sm:max-w-xs">
                <x-order-totals :order="$order" :payment="$order->paid_at ? null : $payment" />
            </dl>
        </div>

        <footer class="mt-10 border-t border-stone-200 pt-4 text-center text-xs text-stone-400">
            {{ $footer }}
        </footer>
    </article>
</x-layouts.print>
