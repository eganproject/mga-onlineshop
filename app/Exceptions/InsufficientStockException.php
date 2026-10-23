<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly string $itemName, public readonly int $available)
    {
        parent::__construct("Stok {$itemName} tidak mencukupi (tersisa {$available}).");
    }
}
