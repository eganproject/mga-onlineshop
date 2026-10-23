<?php

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dikirim setiap kali status pesanan berubah (termasuk saat pesanan dibuat,
 * dengan $from = null). Cocok untuk email/notifikasi.
 */
class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly ?OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly ?string $note = null,
    ) {}
}
