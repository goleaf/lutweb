<?php

namespace App\Color;

use InvalidArgumentException;

final readonly class CubeSize
{
    public function __construct(public int $value)
    {
        if (! in_array($value, [17, 33, 65], true)) {
            throw new InvalidArgumentException('The CUBE size must be 17, 33, or 65.');
        }
    }

    public function rows(): int
    {
        return $this->value ** 3;
    }
}
