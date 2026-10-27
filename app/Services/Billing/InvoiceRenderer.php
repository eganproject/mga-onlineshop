<?php

namespace App\Services\Billing;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\OrderSummary;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Data & keluaran invoice (halaman cetak dan PDF). Nomor invoice untuk
 * pesanan lunas yang belum punya nomor (mis. pesanan lama) diterbitkan di sini.
 */
class InvoiceRenderer
{
    public function __construct(private readonly InvoiceNumberer $numberer) {}

    public function data(Order $order): array
    {
        if ($order->paid_at && ! $order->invoice_number) {
            $this->numberer->issue($order);
        }

        $order->loadMissing('items', 'latestPayment', 'user');
        $settings = BillingSettings::current();
        $cancelled = $order->status === OrderStatus::Cancelled;

        return [
            'order' => $order,
            'payment' => $order->latestPayment,
            'settings' => $settings,
            'company' => $settings->company(),
            'rows' => OrderSummary::rows($order, $order->paid_at ? null : $order->latestPayment),
            'isInvoice' => (bool) $order->invoice_number,
            'title' => $order->invoice_number ? 'INVOICE' : 'TAGIHAN',
            'number' => $order->invoice_number ?? $order->code,
            'status' => match (true) {
                $cancelled => ['DIBATALKAN', 'rose'],
                (bool) $order->paid_at => ['LUNAS', 'emerald'],
                default => ['BELUM DIBAYAR', 'amber'],
            },
            'notes' => $settings->get('invoice_notes'),
            'footer' => $settings->get('invoice_footer') ?: 'Terima kasih telah berbelanja di '.config('shop.full_name').'.',
            'signatory' => array_filter(['name' => $settings->get('signatory_name'), 'title' => $settings->get('signatory_title')]),
        ];
    }

    public function filename(Order $order): string
    {
        return Str::slug(str_replace(['/', '\\'], '-', $order->invoice_number ? 'invoice-'.$order->invoice_number : 'tagihan-'.$order->code)).'.pdf';
    }

    public function pdf(Order $order): Response
    {
        $data = $this->data($order);
        $data['logo'] = $this->logo();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot(public_path());
        $options->setTempDir(storage_path('framework/cache'));
        $options->setFontCache(storage_path('framework/cache'));

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view('orders.invoice-pdf', $data)->render(), 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($order).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function logo(): ?string
    {
        $path = public_path('images/brand/mga-logo.png');

        return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
