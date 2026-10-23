<?php

namespace App\Data;

use JsonSerializable;

/** Satu wilayah (provinsi, kota/kabupaten, atau kecamatan) dari penyedia ongkir. */
final readonly class Region implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $postalCode = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'postal_code' => $this->postalCode,
        ];
    }
}
