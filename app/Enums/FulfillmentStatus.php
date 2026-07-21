<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Revoked = 'revoked';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Ready => 'Ready',
            self::Revoked => 'Revoked',
            self::Failed => 'Failed',
        };
    }
}
