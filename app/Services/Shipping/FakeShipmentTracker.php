<?php

namespace App\Services\Shipping;

use App\Contracts\ShipmentTracker;
use App\Data\TrackingEvent;
use App\Data\TrackingResult;
use App\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Pelacakan tiruan untuk development: perjalanan paket disimulasikan dari
 * waktu kirim, jadi status maju sendiri seiring waktu (sampai ±2 hari).
 */
class FakeShipmentTracker implements ShipmentTracker
{
    /** [jam setelah dikirim, deskripsi, lokasi] — lokasi null = kota tujuan. */
    private const JOURNEY = [
        [0, 'Paket diterima di counter/gudang kurir', 'Gudang asal'],
        [5, 'Paket diproses di pusat sortir', 'Hub asal'],
        [12, 'Paket dalam perjalanan ke kota tujuan', 'Hub asal'],
        [30, 'Paket tiba di gudang kota tujuan', null],
        [40, 'Paket dibawa kurir menuju alamat penerima', null],
        [46, 'Paket diterima', null],
    ];

    public function track(Order $order): TrackingResult
    {
        $shippedAt = CarbonImmutable::parse($order->shipped_at ?? $order->updated_at);
        $now = CarbonImmutable::now();
        $destination = $this->destinationCity($order);
        $events = [];

        foreach (self::JOURNEY as $i => [$hours, $description, $location]) {
            $at = $shippedAt->addHours($hours);

            if ($at->isAfter($now)) {
                break;
            }

            $isLast = $i === array_key_last(self::JOURNEY);
            $events[] = new TrackingEvent(
                at: $at,
                description: $isLast ? "{$description} oleh {$order->recipient_name}" : $description,
                location: $location ?? $destination,
            );
        }

        $delivered = count($events) === count(self::JOURNEY);

        return new TrackingResult(
            delivered: $delivered,
            status: $delivered ? 'DELIVERED' : 'ON PROCESS',
            events: array_reverse($events),
            receiver: $delivered ? $order->recipient_name : null,
            deliveredAt: $delivered ? end($events)->at : null,
            checkedAt: $now,
        );
    }

    private function destinationCity(Order $order): string
    {
        // Alamat tersimpan sebagai "jalan, kecamatan, kota, provinsi, kode pos".
        foreach (array_map('trim', explode(',', $order->shipping_address)) as $part) {
            if (preg_match('/^(Kota|Kabupaten|Kab\.)\s/i', $part)) {
                return $part;
            }
        }

        return 'Kota tujuan';
    }
}
