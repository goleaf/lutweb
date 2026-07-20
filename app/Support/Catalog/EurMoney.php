<?php

namespace App\Support\Catalog;

use InvalidArgumentException;

final class EurMoney
{
    public static function parseDecimalToCents(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match('/^(0|[1-9][0-9]*)(?:\\.([0-9]{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Enter a non-negative EUR amount with at most two decimal places.');
        }

        $euros = (int) $matches[1];
        $cents = str_pad($matches[2] ?? '0', 2, '0');

        return ($euros * 100) + (int) $cents;
    }

    public static function formatCents(int $cents): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Cents must be non-negative.');
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
