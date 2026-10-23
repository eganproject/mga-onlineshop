<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    /** Nama driver, disimpan di kolom payments.gateway. */
    public function name(): string;

    /** Buat transaksi pembayaran untuk pesanan dan simpan sebagai Payment. */
    public function createPayment(Order $order): Payment;

    /**
     * Proses notifikasi (webhook) dari gateway. Mengembalikan Payment yang
     * diperbarui, atau null bila notifikasi tidak valid.
     */
    public function handleNotification(array $payload): ?Payment;
}
