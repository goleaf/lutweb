<?php

namespace App\Enums;

enum LutTransformVersion: string
{
    case V1 = 'lut_transform_v1';

    public function label(): string
    {
        return match ($this) {
            self::V1 => 'LUT Transform V1',
        };
    }
}
