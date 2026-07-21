<?php

namespace App\Enums;

enum CubeGeneratorVersion: string
{
    case V1 = 'cube_generator_v1';

    public function label(): string
    {
        return match ($this) {
            self::V1 => 'CUBE Generator V1',
        };
    }
}
