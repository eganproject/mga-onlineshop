<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
            'products' => $tab === 'produk' ? $this->reports->byProduct($period) : collect(),
            'categories' => $tab === 'kategori' ? $this->reports->byCategory($period) : collect(),
        ]);
    }

    /** Unduh laporan sebagai CSV (dibuka langsung di Excel / Google Sheets). */
    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['penjualan', 'produk', 'kategori', 'pesanan'], true), 404);

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
                yield ['Kode pesanan', 'Tanggal bayar', 'Status', 'Pelanggan', 'Email', 'Jumlah barang', 'Subtotal (Rp)', 'Ongkir (Rp)', 'Total (Rp)', 'Kurir'];
                foreach ($this->reports->orders($period)->lazy(500) as $order) {
                    /** @var Order $order */
                    yield [
                        $order->code,
                        $order->paid_at?->format('Y-m-d H:i'),
                        $order->status->label(),
                        $order->user?->name,
                        $order->user?->email,
                        (int) $order->items_quantity,
                        $order->subtotal,
                        $order->shipping_cost,
                        $order->total,
                        $order->courierLabel(),
                    ];
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
