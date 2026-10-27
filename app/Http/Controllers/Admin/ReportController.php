<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\BillingSettings;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TABS = [
        'penjualan' => 'Penjualan',
        'produk' => 'Per produk',
        'kategori' => 'Per kategori',
        'keuangan' => 'Keuangan & pajak',
    ];

    public function __construct(private readonly SalesReportService $reports) {}

    public function index(Request $request): View
    {
        $tab = ReportPeriod::param($request, 'tab');
        $tab = $tab && array_key_exists($tab, self::TABS) ? $tab : 'penjualan';
        $period = ReportPeriod::fromRequest($request);

        $summary = $this->reports->summary($period);

        return view('admin.reports.index', [
            'tab' => $tab,
            'period' => $period,
            'summary' => $summary,
            'changes' => $this->reports->compare($summary, $this->reports->summary($period->previous())),
            'series' => $tab === 'penjualan' ? $this->reports->timeseries($period) : collect(),
            'statuses' => $tab === 'penjualan' ? $this->reports->statusBreakdown($period) : collect(),
            'vouchers' => $tab === 'penjualan' ? $this->reports->byVoucher($period) : collect(),
            'products' => $tab === 'produk' ? $this->reports->byProduct($period) : collect(),
            'categories' => $tab === 'kategori' ? $this->reports->byCategory($period) : collect(),
            'methods' => $tab === 'keuangan' ? $this->reports->byPaymentMethod($period) : collect(),
        ]);
    }

    /** Unduh laporan sebagai CSV (dibuka langsung di Excel / Google Sheets). */
    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['penjualan', 'produk', 'kategori', 'pesanan', 'pajak', 'metode'], true), 404);

        $period = ReportPeriod::fromRequest($request);
        $filename = sprintf('laporan-%s_%s_%s.csv', $type, $period->from->toDateString(), $period->to->toDateString());

        return response()->streamDownload(function () use ($type, $period) {
            $out = fopen('php://output', 'w');
            // BOM agar Excel membaca UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($this->rows($type, $period) as $row) {
                fputcsv($out, array_map(self::sanitize(...), $row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return iterable<array> */
    private function rows(string $type, ReportPeriod $period): iterable
    {
        switch ($type) {
            case 'penjualan':
                yield ['Periode', 'Pesanan dibayar', 'Produk terjual (pcs)', 'Pendapatan (Rp)'];
                foreach ($this->reports->timeseries($period) as $row) {
                    yield [$row['label'], $row['orders'], $row['items'], $row['revenue']];
                }
                break;

            case 'produk':
                yield ['Produk', 'SKU', 'Terjual (pcs)', 'Jumlah pesanan', 'Penjualan (Rp)', 'Kontribusi (%)'];
                foreach ($this->reports->byProduct($period) as $row) {
                    yield [$row->name, $row->sku, $row->quantity, $row->orders, $row->revenue, $row->share];
                }
                break;

            case 'kategori':
                yield ['Kategori', 'Jumlah produk', 'Terjual (pcs)', 'Jumlah pesanan', 'Penjualan (Rp)', 'Kontribusi (%)'];
                foreach ($this->reports->byCategory($period) as $row) {
                    yield [$row->name, $row->products, $row->quantity, $row->orders, $row->revenue, $row->share];
                }
                break;

            case 'pesanan':
                yield ['Kode pesanan', 'No. invoice', 'Tanggal bayar', 'Status', 'Pelanggan', 'Email', 'Jumlah barang', 'Subtotal (Rp)', 'Ongkir (Rp)', 'Voucher', 'Diskon (Rp)', 'Biaya layanan (Rp)', 'PPN (Rp)', 'Pembulatan (Rp)', 'Total pesanan (Rp)', 'Metode bayar', 'Biaya transaksi pembeli (Rp)', 'Dibayar pelanggan (Rp)', 'Potongan Midtrans (Rp)', 'Bersih diterima (Rp)', 'Kurir'];
                foreach ($this->reports->orders($period)->lazy(500) as $order) {
                    /** @var Order $order */
                    yield [
                        $order->code,
                        $order->invoice_number,
                        $order->paid_at?->format('Y-m-d H:i'),
                        $order->status->label(),
                        $order->user?->name,
                        $order->user?->email,
                        (int) $order->items_quantity,
                        $order->subtotal,
                        $order->shipping_cost,
                        $order->voucher_code,
                        $order->discountTotal(),
                        $order->service_fee,
                        $order->tax_amount,
                        $order->rounding,
                        $order->total,
                        $order->paymentMethodLabel(),
                        $order->payment_fee,
                        $order->amountPaid(),
                        $order->gateway_fee,
                        $order->netReceived(),
                        $order->courierLabel(),
                    ];
                }
                break;

            case 'pajak':
                yield ['No. invoice', 'Tanggal invoice', 'Kode pesanan', 'Nama pembeli', 'NPWP pembeli', 'Cara hitung', 'Tarif (%)', 'DPP (Rp)', 'PPN (Rp)', 'Total pesanan (Rp)'];
                foreach ($this->reports->taxedOrders($period)->lazy(500) as $order) {
                    /** @var Order $order */
                    yield [
                        $order->invoice_number,
                        ($order->invoice_issued_at ?? $order->paid_at)?->format('Y-m-d'),
                        $order->code,
                        $order->billedName(),
                        BillingSettings::formatNpwp($order->billing_npwp),
                        ($order->tax_inclusive ? 'Termasuk harga' : 'Ditambahkan').($order->tax_dpp_other ? ', DPP 11/12' : ''),
                        $order->tax_rate,
                        $order->tax_base,
                        $order->tax_amount,
                        $order->total,
                    ];
                }
                break;

            case 'metode':
                yield ['Metode bayar', 'Pesanan', 'Dibayar pelanggan (Rp)', 'Biaya transaksi pembeli (Rp)', 'Potongan Midtrans (Rp)', 'Bersih diterima (Rp)', 'Porsi (%)'];
                foreach ($this->reports->byPaymentMethod($period) as $row) {
                    yield [$row->label, $row->orders, $row->paid, $row->payment_fee, $row->gateway_fee, $row->net, $row->share];
                }
                break;
        }
    }

    /**
     * Cegah CSV/formula injection: teks yang diawali =, +, -, @ diberi tanda
     * kutip agar tidak dieksekusi sebagai rumus oleh Excel.
     */
    private static function sanitize(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
