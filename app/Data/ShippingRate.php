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
        if (! $this->etd || ! preg_match('/\d+/', $this->etd, $m)) {
            return null;
        }

        // Kurir instan (estimasi jam/menit) = sampai hari ini.
        return preg_match('/jam|menit/', $this->etd) ? 0 : (int) $m[0];
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
