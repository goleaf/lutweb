<?php

namespace App\Color;

use InvalidArgumentException;

final readonly class RgbVector
{
    public function __construct(
        public float $red,
        public float $green,
        public float $blue,
    ) {
        if (! is_finite($red) || ! is_finite($green) || ! is_finite($blue)) {
            throw new InvalidArgumentException('RGB vector channels must be finite.');
        }
    }
}
