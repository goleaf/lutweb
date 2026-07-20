<?php

namespace App\Enums;

enum ProductType: string
{
    case SingleLut = 'single_lut';
    case Bundle = 'bundle';
    case FreeLut = 'free_lut';

    public function label(): string
    {
        return match ($this) {
            self::SingleLut => 'Single LUT',
            self::Bundle => 'Bundle',
            self::FreeLut => 'Free LUT',
        };
    }
}
