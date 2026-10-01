<?php

namespace App\Exceptions;

use RuntimeException;

class NegativeStockException extends RuntimeException
{
    public static function forProduct(string $productName, $quantityAfter): self
    {
        return new self("Insufficient stock for {$productName}. Resulting quantity would be {$quantityAfter}.");
    }

    public static function forShortage(string $productName, float $available, float $requested): self
    {
        $availableText = rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.');
        $requestedText = rtrim(rtrim(number_format($requested, 4, '.', ''), '0'), '.');

        return new self("Insufficient stock for {$productName}. Available: {$availableText}. Requested: {$requestedText}.");
    }
}
