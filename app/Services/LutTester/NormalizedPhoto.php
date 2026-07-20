<?php

namespace App\Services\LutTester;

final readonly class NormalizedPhoto
{
    public function __construct(
        public string $path,
        public int $width,
        public int $height,
    ) {}
}
