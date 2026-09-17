<?php

namespace App\Exceptions;

use RuntimeException;

class NegativeStockException extends RuntimeException
{
    public static function forProduct(string $productName, $quantityAfter): self
    {
        return new self("Insufficient stock for {$productName}. Resulting quantity would be {$quantityAfter}.");
    }
}
