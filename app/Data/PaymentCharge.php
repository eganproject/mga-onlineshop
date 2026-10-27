<?php

namespace App\Data;

use App\Enums\PaymentMethod;

/**
 * Nominal satu tagihan pembayaran: total pesanan + biaya transaksi yang
 * dibebankan ke pembeli (bila ada) untuk metode terpilih.
 */
final readonly class PaymentCharge
{
    /**
     * @param  ?PaymentMethod  $method  null = pembeli memilih sendiri di Midtrans
     * @param  int  $fee  biaya transaksi yang dibayar pembeli (sudah termasuk di amount)
     * @param  int  $gatewayFee  perkiraan potongan Midtrans (0 bila metode belum diketahui)
     * @param  ?list<string>  $enabledPayments  kode enabled_payments Snap; null = semua
     */
    public function __construct(
        public int $amount,
        public ?PaymentMethod $method = null,
        public int $fee = 0,
        public int $gatewayFee = 0,
        public ?array $enabledPayments = null,
        public string $feeLabel = 'Biaya transaksi',
    ) {}
}
