<?php

namespace App\Services\CustomLutBuilds;

final readonly class PackageName
{
    public function __construct(
        public string $displayName,
        public string $stem,
    ) {}

    public function cubeFilename(int $size): string
    {
        return $this->stem.'-'.$size.'.cube';
    }

    public function zipFilename(): string
    {
        return $this->stem.'.zip';
    }

    public function title(): string
    {
        return str_replace(['"', "\r", "\n"], '', $this->stem);
    }
}
