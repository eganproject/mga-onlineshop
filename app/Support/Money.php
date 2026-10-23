<?php

namespace App\Support;

class Money
{
    /** Format angka rupiah utuh, contoh: 150000 -> "Rp150.000". */
    public static function rupiah(int|float|null $amount): string
    {
        return 'Rp'.number_format((int) $amount, 0, ',', '.');
    }

    /** Format ringkas untuk sumbu grafik, contoh: 1250000 -> "Rp1,3 jt". */
    public static function compact(int|float|null $amount): string
    {
        $amount = (int) $amount;
        $abs = abs($amount);

        [$value, $suffix] = match (true) {
            $abs >= 1_000_000_000 => [$amount / 1_000_000_000, ' M'],
            $abs >= 1_000_000 => [$amount / 1_000_000, ' jt'],
            $abs >= 1_000 => [$amount / 1_000, ' rb'],
            default => [$amount, ''],
        };

        $decimals = $suffix !== '' && abs($value) < 10 && fmod(abs($value), 1) >= 0.05 ? 1 : 0;

        return 'Rp'.number_format($value, $decimals, ',', '.').$suffix;
    }
}
