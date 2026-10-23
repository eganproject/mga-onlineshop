<?php

namespace App\Data;

use App\Enums\PaymentStatus;

/**
 * Status transaksi dari gateway (webhook atau cek status), sudah
 * diterjemahkan ke istilah aplikasi. Penerapannya di PaymentService.
 */
final readonly class PaymentUpdate
{
    /**
     * @param  ?PaymentStatus  $status  null = tidak mengubah status (mis. partial refund)
     */
    public function __construct(
        public string $externalId,
        public ?PaymentStatus $status,
        public ?int $amount = null,
        public ?string $reference = null,
        public ?string $paymentType = null,
        public array $payload = [],
    ) {}
}
