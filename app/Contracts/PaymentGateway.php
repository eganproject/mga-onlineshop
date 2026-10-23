<?php

namespace App\Contracts;

use App\Data\PaymentUpdate;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonInterface;

/**
 * Driver payment gateway. Driver hanya bicara dengan API gateway dan
 * menerjemahkan statusnya; aturan pesanan & stok ada di PaymentService.
 */
interface PaymentGateway
{
    /** Nama driver, disimpan di kolom payments.gateway. */
    public function name(): string;

    /**
     * Buat tagihan baru untuk pesanan dan simpan sebagai Payment.
     * $expiresAt = batas bayar tagihan ini (biasanya payment_due_at pesanan).
     */
    public function createPayment(Order $order, CarbonInterface $expiresAt): Payment;

    /**
     * Verifikasi dan terjemahkan notifikasi (webhook). Null bila tanda
     * tangan/isi tidak valid.
     */
    public function parseNotification(array $payload): ?PaymentUpdate;

    /** Ambil status terkini dari gateway. Null bila transaksi belum ada di gateway. */
    public function fetchStatus(Payment $payment): ?PaymentUpdate;

    /** Batalkan transaksi yang masih pending di gateway (best effort). */
    public function cancel(Payment $payment): void;
}
