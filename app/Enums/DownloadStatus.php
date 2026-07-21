<?php

namespace App\Enums;

enum DownloadStatus: string
{
    case Started = 'started';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Started => 'Started',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
