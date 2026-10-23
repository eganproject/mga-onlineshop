<?php

namespace App\Contracts;

use App\Data\TrackingResult;
use App\Models\Order;

/** Pelacakan resi pengiriman pesanan. Driver mengikuti shop.shipping.driver. */
interface ShipmentTracker
{
    /**
     * Lacak paket berdasarkan kurir dan nomor resi pesanan.
     *
     * @throws \Throwable bila API gagal; ditangani oleh TrackingService.
     */
    public function track(Order $order): TrackingResult;
}
