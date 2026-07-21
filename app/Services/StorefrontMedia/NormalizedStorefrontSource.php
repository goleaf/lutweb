<?php

namespace App\Services\StorefrontMedia;

final readonly class NormalizedStorefrontSource
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $mimeType,
        public int $sizeBytes,
        public int $width,
        public int $height,
        public string $sha256,
    ) {}
}
