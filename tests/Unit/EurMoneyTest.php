<?php

use App\Support\Catalog\EurMoney;

test('EUR decimal parser handles supported decimal forms', function (string $decimal, int $cents) {
    expect(EurMoney::parseDecimalToCents($decimal))->toBe($cents)
        ->and(EurMoney::formatCents($cents))->toBe(match ($decimal) {
            '19.9' => '19.90',
            '19' => '19.00',
            default => $decimal,
        });
})->with([
    'zero' => ['0.00', 0],
    'whole value' => ['19', 1900],
    'one decimal' => ['19.9', 1990],
    'two decimals' => ['19.99', 1999],
]);

test('EUR decimal parser rejects invalid values', function (string $decimal) {
    expect(fn () => EurMoney::parseDecimalToCents($decimal))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'negative' => ['-1.00'],
    'malformed' => ['ten'],
    'too many decimals' => ['19.999'],
    'empty' => [''],
]);
