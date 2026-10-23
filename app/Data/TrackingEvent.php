<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/** Satu baris riwayat perjalanan paket dari kurir. */
final readonly class TrackingEvent
{
    public function __construct(
        public ?CarbonImmutable $at,
        public string $description,
        public ?string $location = null,
    ) {}

    public function toArray(): array
    {
        return [
            'at' => $this->at?->toIso8601String(),
            'description' => $this->description,
            'location' => $this->location,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            at: filled($data['at'] ?? null) ? CarbonImmutable::parse($data['at']) : null,
            description: (string) ($data['description'] ?? ''),
            location: $data['location'] ?? null,
        );
    }
}
