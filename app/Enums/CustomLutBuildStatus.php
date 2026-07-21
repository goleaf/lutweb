<?php

namespace App\Enums;

enum CustomLutBuildStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Superseded = 'superseded';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Ready => 'Ready',
            self::Superseded => 'Superseded',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
        };
    }
}
