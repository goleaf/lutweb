<?php

namespace App\Enums;

enum StorefrontImageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Stale = 'stale';
}
