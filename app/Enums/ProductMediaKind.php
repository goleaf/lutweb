<?php

namespace App\Enums;

enum ProductMediaKind: string
{
    case Cover = 'cover';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Cover => 'Cover',
            self::Gallery => 'Gallery',
        };
    }
}
