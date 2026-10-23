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
            self::Percent => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            self::Fixed => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::FreeShipping => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        };
    }
}
