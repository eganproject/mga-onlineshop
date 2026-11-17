<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Activity\ActivityLogger;
use App\Services\Warehouse\WarehouseApiException;
use App\Services\Warehouse\WarehouseOutbound;
use Illuminate\Http\RedirectResponse;

/** Kirim ulang / cek status outbound manual penjualan manual di warehouse29. */
class OrderWarehouseController extends Controller
{
    public function __construct(private readonly WarehouseOutbound $outbound) {}

    public function send(Order $order, ActivityLogger $activity): RedirectResponse
    {
        if (! $order->paid_at) {
            return back()->with('error', 'Hanya pesanan yang sudah lunas yang dikirim ke gudang.');
        }

        if (! $this->outbound->shouldSend($order)) {
            return back()->with('info', 'Pesanan ini sudah tercatat di gudang.');
        }

        try {
            $sent = $this->outbound->send($order);
        } catch (WarehouseApiException $e) {
            $order->forceFill(['warehouse_outbound_status' => Order::OUTBOUND_FAILED, 'warehouse_outbound_error' => $e->getMessage()])->saveQuietly();

            return back()->with('error', $e->getMessage());
        }

        $activity->log('order.warehouse_sent', "Mengirim pesanan {$order->code} ke gudang", $order);

        return $sent
            ? back()->with('success', "Pesanan {$order->code} tercatat di gudang ({$order->warehouse_outbound_code}), menunggu persetujuan admin gudang.")
            : back()->with('error', $order->warehouse_outbound_error);
    }

    public function refresh(Order $order): RedirectResponse
    {
        if (! $order->warehouse_outbound_code && ! in_array($order->warehouse_outbound_status, [Order::OUTBOUND_PENDING, Order::OUTBOUND_APPROVED], true)) {
            return back()->with('info', 'Pesanan ini belum dikirim ke gudang.');
        }

        try {
            $this->outbound->refresh($order);
        } catch (WarehouseApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status gudang: '.$order->outboundStatusLabel()[0].'.');
    }
}
