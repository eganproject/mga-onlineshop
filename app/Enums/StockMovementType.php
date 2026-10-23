<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Initial = 'initial';
    case Restock = 'restock';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Cancellation = 'cancellation';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Stok awal',
            self::Restock => 'Barang masuk',
            self::Adjustment => 'Penyesuaian',
            self::Sale => 'Penjualan',
            self::Cancellation => 'Pesanan batal',
        };
    }

    /** Jenis yang boleh dipilih admin saat mengubah stok manual. */
    public static function manual(): array
    {
        return [self::Restock, self::Adjustment];
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Initial, self::Restock => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Adjustment => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::Sale => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::Cancellation => 'bg-stone-100 text-stone-600 ring-stone-500/20',
        };
    }
}
