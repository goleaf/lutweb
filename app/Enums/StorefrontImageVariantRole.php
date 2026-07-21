<?php

namespace App\Enums;

enum StorefrontImageVariantRole: string
{
    case Media = 'media';
    case Before = 'before';
    case After = 'after';
}
