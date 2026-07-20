<?php

namespace App\Enums;

enum ProductVersionStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Retired => 'Retired',
        };
    }
}
