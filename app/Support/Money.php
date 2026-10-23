<?php

namespace App\Support;

class Money
{
    /** Format angka rupiah utuh, contoh: 150000 -> "Rp150.000". */
    public static function rupiah(int|float|null $amount): string
    {
        return 'Rp'.number_format((int) $amount, 0, ',', '.');
    }
}
