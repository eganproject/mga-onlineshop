<?php

namespace App\Console\Commands;

use App\Services\Payment\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shop:expire-payments')]
#[Description('Batalkan pesanan yang lewat batas bayar dan kembalikan stoknya')]
class ExpirePaymentsCommand extends Command
{
    public function handle(PaymentService $payments): int
    {
        $count = $payments->expireOverdueOrders();

        $this->components->info("{$count} pesanan kedaluwarsa dibatalkan.");

        return self::SUCCESS;
    }
}
