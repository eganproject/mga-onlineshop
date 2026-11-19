<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShipmentException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Activity\ActivityLogger;
use App\Services\Shipping\BiteshipShipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;
use Throwable;

/** Pesan kurir lewat Biteship dari detail pesanan dan cetak label resinya. */
class OrderShipmentController extends Controller
{
    public function __construct(private readonly BiteshipShipment $shipments) {}

    public function store(Request $request, Order $order, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['nullable', Rule::in([BiteshipShipment::METHOD_PICKUP, BiteshipShipment::METHOD_DROP_OFF])],
        ]);

        try {
            $this->shipments->create($order->load('items'), $data['method'] ?? BiteshipShipment::METHOD_PICKUP);
        } catch (ShipmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $activity->log('order.shipment_created', "Membuat pengiriman Biteship untuk pesanan {$order->code}", $order, [
            'shipment_id' => $order->shipment_id,
            'tracking_number' => $order->tracking_number,
        ]);

        return $order->tracking_number
            ? back()->with('success', "Pengiriman dibuat, nomor resi {$order->tracking_number}. Label resi siap dicetak.")
            : back()->with('info', 'Pengiriman dibuat di Biteship, nomor resi belum terbit. Tekan "Cek status" beberapa saat lagi.');
    }

    public function refresh(Order $order): RedirectResponse
    {
        try {
            $this->shipments->refresh($order);
        } catch (ShipmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status pengiriman: '.BiteshipShipment::statusLabel($order->shipment_status)[0].'.');
    }

    /** Label resi siap cetak (A6) dengan barcode nomor resi. */
    public function label(Order $order): View
    {
        abort_if(blank($order->tracking_number), 404);

        return view('orders.shipping-label', [
            'order' => $order->load('items'),
            'barcode' => $this->barcode($order->tracking_number),
        ]);
    }

    /** Barcode Code 128 (SVG); null bila resi berisi karakter yang tidak didukung. */
    private function barcode(string $value): ?string
    {
        try {
            $svg = (new SvgRenderer)->setSvgType(SvgRenderer::TYPE_SVG_INLINE)->render((new TypeCode128)->getBarcode($value), 320, 64);
        } catch (Throwable) {
            return null;
        }

        return $svg;
    }
}
