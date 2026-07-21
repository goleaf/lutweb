<?php

namespace App\Enums;

enum PayPalWebhookVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Invalid => 'Invalid',
        };
    }
}
