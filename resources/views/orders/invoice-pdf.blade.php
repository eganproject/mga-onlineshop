{{-- Invoice versi PDF (Dompdf: CSS sederhana, tanpa Tailwind). Data sama dengan orders/invoice. --}}
@php
    use App\Services\Billing\BillingSettings;
    use App\Support\Money;
    use App\Support\OrderSummary;

    $statusColor = ['emerald' => '#047857', 'rose' => '#be123c', 'amber' => '#b45309'][$status[1]];
    $methodLabel = $order->paid_at ? $order->paymentMethodLabel() : $payment?->methodLabel();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $number }}</title>
    <style>
        @page { margin: 14mm 14mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1c1917; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .muted { color: #78716c; }
        .small { font-size: 8pt; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .brand { color: #21939F; }
        h1 { font-size: 20pt; margin: 0; letter-spacing: 1px; }
        .status { display: inline-block; margin-top: 6px; padding: 3px 10px; border-radius: 10px; font-size: 8pt; font-weight: bold; color: #fff; }
        .section { border-top: 1px solid #e7e5e4; padding-top: 10px; margin-top: 12px; }
        .label { font-size: 7.5pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #a8a29e; }
        .items th { font-size: 7.5pt; letter-spacing: .5px; text-transform: uppercase; color: #78716c; border-bottom: 1px solid #d6d3d1; padding: 6px 4px; text-align: left; }
        .items td { border-bottom: 1px solid #f5f5f4; padding: 6px 4px; }
        .totals td { padding: 3px 0; }
        .totals .discount td { color: #047857; }
        .totals .info td { color: #78716c; font-size: 8pt; }
        .totals .subtotal td { border-top: 1px solid #e7e5e4; padding-top: 5px; font-weight: bold; }
        .totals .total td { border-top: 1.5px solid #1c1917; padding-top: 6px; font-weight: bold; font-size: 11.5pt; }
        .taxbox { background: #f5f5f4; padding: 8px 10px; border-radius: 6px; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #a8a29e; }
    </style>
</head>
<body>
    <div class="footer">{{ $footer }}</div>

    <table>
        <tr>
            <td style="width: 55%;">
                @if ($logo)<img src="{{ $logo }}" alt="" style="height: 40px;"><br>@endif
                <div class="bold" style="margin-top: 6px; font-size: 10.5pt;">{{ $company['name'] }}</div>
                @if ($company['npwp'])<div class="small">NPWP {{ BillingSettings::formatNpwp($company['npwp']) }}@if ($company['is_pkp']) · PKP @endif</div>@endif
                @if ($company['address'])<div class="small muted">{{ $company['address'] }}</div>@endif
                <div class="small muted">{{ collect([$company['phone'], $company['email']])->filter()->implode(' · ') }}</div>
            </td>
            <td class="right">
                <h1>{{ $title }}</h1>
                <div class="mono bold" style="font-size: 10.5pt;">{{ $number }}</div>
                @if ($isInvoice)<div class="small muted">Tanggal invoice: {{ $order->invoice_issued_at->translatedFormat('d F Y') }}</div>@endif
                <div class="small muted">No. pesanan: <span class="mono">{{ $order->code }}</span></div>
                <div class="small muted">Tanggal pesan: {{ $order->created_at->translatedFormat('d F Y') }}</div>
                @if ($order->paid_at)
                    <div class="small muted">Tanggal bayar: {{ $order->paid_at->translatedFormat('d F Y') }}</div>
                @elseif ($status[0] === 'BELUM DIBAYAR')
                    <div class="small muted">Bayar sebelum: {{ $order->paymentDeadline()->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i') }} WIB</div>
                @endif
                <span class="status" style="background: {{ $statusColor }};">{{ $status[0] }}</span>
            </td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td style="width: 55%; padding-right: 12px;">
                <div class="label">Ditagihkan kepada</div>
                <div class="bold">{{ $order->billedName() }}</div>
                @if ($order->billing_npwp)<div>NPWP {{ BillingSettings::formatNpwp($order->billing_npwp) }}</div>@endif
                @if ($order->billing_name)
                    <div class="muted">{{ $order->billing_address ?: $order->shipping_address }}</div>
                    <div class="muted">{{ $order->billing_email ?: $order->user?->email }}</div>
                    <div class="muted">u.p. {{ $order->recipient_name }} · {{ $order->recipient_phone }}</div>
                @else
                    <div class="muted">{{ $order->recipient_phone }}@if ($order->user?->email) · {{ $order->user->email }}@endif</div>
                    <div class="muted">{{ $order->shipping_address }}</div>
                @endif
            </td>
            <td class="right">
                <div class="label">Pengiriman & pembayaran</div>
                <div class="bold">{{ $order->courierLabel() ?? '—' }}</div>
                @if ($order->billing_name)<div class="muted">Dikirim ke {{ $order->recipient_name }}</div>@endif
                @if ($order->tracking_number)<div class="mono muted">Resi {{ $order->tracking_number }}</div>@endif
                @if ($methodLabel)<div class="muted" style="margin-top: 4px;">Metode bayar: {{ $methodLabel }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items section">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th>Produk</th>
                <th class="center" style="width: 8%;">Qty</th>
                <th class="right" style="width: 17%;">Harga</th>
                <th class="right" style="width: 18%;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td>
                        <div class="bold">{{ $item->product_name }}</div>
                        @if ($item->variant_name || $item->sku)<div class="small muted">{{ collect([$item->variant_name, $item->sku])->filter()->implode(' · ') }}</div>@endif
                    </td>
                    <td class="center">{{ $item->quantity }}</td>
                    <td class="right">{{ Money::rupiah($item->price) }}</td>
                    <td class="right bold">{{ Money::rupiah($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 52%; padding-right: 16px;" class="small">
                @if ($order->hasTax())
                    <div class="taxbox">
                        <div class="bold">Rincian pajak</div>
                        <table>
                            <tr><td>Dasar Pengenaan Pajak{{ $order->tax_dpp_other ? ' (nilai lain 11/12)' : '' }}</td><td class="right">{{ Money::rupiah($order->tax_base) }}</td></tr>
                            <tr><td>{{ $order->tax_label ?: 'PPN' }} {{ BillingSettings::percent($order->tax_rate) }}</td><td class="right">{{ Money::rupiah($order->tax_amount) }}</td></tr>
                        </table>
                        <div class="muted">{{ $order->tax_inclusive ? 'Harga sudah termasuk pajak.' : 'Pajak ditambahkan ke tagihan.' }}</div>
                    </div>
                @endif
                @if ($notes)<div class="muted" style="margin-top: 8px;">{!! nl2br(e($notes)) !!}</div>@endif
            </td>
            <td>
                <table class="totals">
                    @foreach ($rows as $row)
                        <tr class="{{ $row['tone'] }}">
                            <td>{{ $row['label'] }}@if (! empty($row['note'])) <span class="mono small">{{ $row['note'] }}</span>@endif</td>
                            <td class="right">{{ OrderSummary::format($row) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @if ($signatory)
        <table style="margin-top: 28px;">
            <tr>
                <td style="width: 62%;"></td>
                <td class="center">
                    <div class="muted">Hormat kami,</div>
                    <div style="height: 54px;"></div>
                    <div class="bold" style="border-top: 1px solid #a8a29e; padding-top: 3px;">{{ $signatory['name'] ?? $company['name'] }}</div>
                    @isset($signatory['title'])<div class="small muted">{{ $signatory['title'] }}</div>@endisset
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
