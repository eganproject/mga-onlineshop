<?php

namespace App\Enums;

enum ImportType: string
{
    case Products = 'products';
    case Stock = 'stock';

    public function label(): string
    {
        return match ($this) {
            self::Products => 'Impor produk',
            self::Stock => 'Update stok massal',
        };
    }
}
