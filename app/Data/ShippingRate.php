<?php

namespace App\Data;

/** Satu opsi ongkos kirim (kurir + layanan). */
final readonly class ShippingRate
{
    public function __construct(
        public string $courier,
        public string $courierName,
        public string $service,
        public string $description,
        public int $cost,
        public ?string $etd = null,
    ) {}

    /** Kunci unik untuk dipilih di form checkout, contoh: "jne|REG". */
    public function key(): string
    {
        return $this->courier.'|'.$this->service;
    }
}
