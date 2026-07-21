<?php

namespace App\Services\PayPal;

use App\Support\Catalog\EurMoney;
use InvalidArgumentException;

class ParsePayPalMoney
{
    /**
     * @param  array<string, mixed>  $money
     */
    public function cents(array $money, string $expectedCurrency = 'EUR'): int
    {
        if (($money['currency_code'] ?? null) !== $expectedCurrency) {
            throw new InvalidArgumentException('Unexpected PayPal currency.');
        }

        if (! is_string($money['value'] ?? null)) {
            throw new InvalidArgumentException('Missing PayPal amount.');
        }

        return EurMoney::parseDecimalToCents($money['value']);
    }
}
