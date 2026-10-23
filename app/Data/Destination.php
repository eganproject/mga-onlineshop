<?php

namespace App\Data;

use JsonSerializable;

/** Lokasi tujuan pengiriman (biasanya level kecamatan). */
final readonly class Destination implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $label,
        public string $province,
        public string $city,
        public ?string $district = null,
        public ?string $postalCode = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'postal_code' => $this->postalCode,
        ];
    }
}
