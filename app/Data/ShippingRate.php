<?php

namespace App\Data;

use App\Support\Money;
use JsonSerializable;

/** Satu opsi ongkos kirim (kurir + layanan). */
final readonly class ShippingRate implements JsonSerializable
{
    public function __construct(
        public string $courier,
        public string $courierName,
        public string $service,
        public string $description,
        public int $cost,
        public ?string $etd = null,
    ) {}

    /** Data mentah untuk cache (cache Laravel tidak menyimpan objek). */
    public function toArray(): array
    {
        return [
            'courier' => $this->courier,
            'courierName' => $this->courierName,
            'service' => $this->service,
            'description' => $this->description,
            'cost' => $this->cost,
            'etd' => $this->etd,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    /** Kunci unik untuk dipilih di form checkout, contoh: "jne|REG". */
    public function key(): string
    {
        return $this->courier.'|'.$this->service;
    }

    /** Estimasi tercepat dalam hari (angka pertama di etd), untuk penanda "Tercepat". */
    public function minDays(): ?int
    {
        return $this->etd && preg_match('/\d+/', $this->etd, $m) ? (int) $m[0] : null;
    }

    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key(),
            'courier' => $this->courier,
            'courier_name' => $this->courierName,
            'service' => $this->service,
            'description' => $this->description,
            'cost' => $this->cost,
            'cost_label' => Money::rupiah($this->cost),
            'etd' => $this->etd,
            'min_days' => $this->minDays(),
        ];
    }
}
