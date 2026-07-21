<?php

namespace App\Color;

use InvalidArgumentException;

final readonly class NormalizedRgb
{
    public function __construct(
        public float $red,
        public float $green,
        public float $blue,
    ) {
        $this->assertChannel($red, 'red');
        $this->assertChannel($green, 'green');
        $this->assertChannel($blue, 'blue');
    }

    /**
     * @return array{red: float, green: float, blue: float}
     */
    public function toArray(): array
    {
        return [
            'red' => $this->red,
            'green' => $this->green,
            'blue' => $this->blue,
        ];
    }

    private function assertChannel(float $value, string $name): void
    {
        if (! is_finite($value) || $value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException("The {$name} RGB channel must be finite and normalized from 0 through 1.");
        }
    }
}
