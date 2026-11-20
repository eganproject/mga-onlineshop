<?php

namespace App\Enums;

enum VoucherType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Diskon persen',
            self::Fixed => 'Potongan nominal',
            self::FreeShipping => 'Gratis ongkir',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            // Jenis bukan status: netral supaya warna hanya dipakai untuk status.
            self::Percent, self::Fixed, self::FreeShipping => 'bg-stone-100 text-stone-600 ring-stone-500/20',
        };
    }
}
