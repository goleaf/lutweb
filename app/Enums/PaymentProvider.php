<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case PayPal = 'paypal';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::PayPal => 'PayPal',
            self::Free => 'Free',
        };
    }
}
