<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu Pembayaran',
            self::Paid => 'Dibayar',
            self::Processing => 'Diproses',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Sampai Tujuan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Penjelasan singkat untuk linimasa pelanggan. */
    public function description(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pesanan dibuat, menunggu pembayaran.',
            self::Paid => 'Pembayaran diterima. Pesanan akan segera diproses.',
            self::Processing => 'Pesanan sedang dikemas oleh tim gudang.',
            self::Shipped => 'Paket diserahkan ke kurir.',
            self::Delivered => 'Paket sudah sampai di alamat tujuan.',
            self::Completed => 'Pesanan selesai. Terima kasih sudah berbelanja!',
            self::Cancelled => 'Pesanan dibatalkan.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PendingPayment => 'receipt',
            self::Paid => 'credit-card',
            self::Processing => 'cube',
            self::Shipped => 'truck',
            self::Delivered => 'map-pin',
            self::Completed => 'check-circle',
            self::Cancelled => 'x-circle',
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
            self::Delivered => 'bg-teal-50 text-teal-700 ring-teal-600/20',
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
            self::Shipped => [self::Delivered],
            self::Delivered => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->nextStatuses(), true);
    }

    /** Pesanan sudah dibayar dan tidak batal: dihitung sebagai omzet. */
    public static function revenueStatuses(): array
    {
        return [self::Paid, self::Processing, self::Shipped, self::Delivered, self::Completed];
    }

    /**
     * Stoknya sudah dipotong di toko tetapi barangnya belum keluar dari gudang.
     * Dipakai sinkron warehouse29: stok toko = stok gudang − pesanan ini.
     */
    public static function awaitingShipmentStatuses(): array
    {
        return [self::PendingPayment, self::Paid, self::Processing];
    }

    /** Sudah diserahkan ke kurir, jadi resinya bisa dilacak. */
    public function isTrackable(): bool
    {
        return in_array($this, [self::Shipped, self::Delivered, self::Completed], true);
    }

    /** Pelanggan boleh menekan "Pesanan diterima". */
    public function canBeConfirmedByCustomer(): bool
    {
        return in_array($this, [self::Shipped, self::Delivered], true);
    }

    /** Langkah utama yang ditampilkan di progres pesanan pelanggan. */
    public static function progressSteps(): array
    {
        return [self::PendingPayment, self::Paid, self::Processing, self::Shipped, self::Delivered, self::Completed];
    }
}
