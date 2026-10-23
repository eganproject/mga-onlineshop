<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Paid => 'Berhasil',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
            self::Refunded => 'Dikembalikan',
        };
    }

    /** Tagihan sudah selesai tanpa pembayaran masuk. */
    public function isUnpaidFinal(): bool
    {
        return in_array($this, [self::Failed, self::Expired, self::Cancelled], true);
    }

    /**
     * Notifikasi gateway bisa datang ganda atau tidak berurutan. Hanya
     * perubahan ini yang diterima; selebihnya diabaikan (idempoten).
     */
    public function canMoveTo(self $status): bool
    {
        return match ($this) {
            self::Pending => $status !== self::Pending,
            // Pembayaran yang terlambat masuk tetap dicatat.
            self::Failed, self::Expired, self::Cancelled => $status === self::Paid,
            self::Paid => $status === self::Refunded,
            self::Refunded => false,
        };
    }
}
