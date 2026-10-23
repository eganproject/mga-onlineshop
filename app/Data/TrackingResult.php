<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/** Hasil lacak resi: status terakhir dan riwayat perjalanan (terbaru dulu). */
final readonly class TrackingResult
{
    /**
     * @param  list<TrackingEvent>  $events
     */
    public function __construct(
        public bool $delivered,
        public ?string $status = null,
        public array $events = [],
        public ?string $receiver = null,
        public ?CarbonImmutable $deliveredAt = null,
        public ?CarbonImmutable $checkedAt = null,
    ) {}

    public function latest(): ?TrackingEvent
    {
        return $this->events[0] ?? null;
    }

    /** Data mentah untuk cache (cache Laravel tidak menyimpan objek). */
    public function toArray(): array
    {
        return [
            'delivered' => $this->delivered,
            'status' => $this->status,
            'events' => array_map(fn (TrackingEvent $e) => $e->toArray(), $this->events),
            'receiver' => $this->receiver,
            'deliveredAt' => $this->deliveredAt?->toIso8601String(),
            'checkedAt' => $this->checkedAt?->toIso8601String(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            delivered: (bool) ($data['delivered'] ?? false),
            status: $data['status'] ?? null,
            events: array_map(fn (array $e) => TrackingEvent::fromArray($e), $data['events'] ?? []),
            receiver: $data['receiver'] ?? null,
            deliveredAt: filled($data['deliveredAt'] ?? null) ? CarbonImmutable::parse($data['deliveredAt']) : null,
            checkedAt: filled($data['checkedAt'] ?? null) ? CarbonImmutable::parse($data['checkedAt']) : null,
        );
    }
}
