<?php

namespace App\Enums;

enum PayPalWebhookProcessingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Processed => 'Processed',
            self::Ignored => 'Ignored',
            self::Failed => 'Failed',
        };
    }
}
