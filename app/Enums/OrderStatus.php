<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu Pembayaran',
            self::Paid => 'Dibayar',
            self::Processing => 'Diproses',
            self::Shipped => 'Dikirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Kelas Tailwind untuk badge status. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PendingPayment => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::Paid => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::Processing => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
            self::Shipped => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            self::Completed => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Cancelled => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }

    /** Status berikutnya yang boleh dipilih admin dari status ini. */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->nextStatuses(), true);
    }
}
