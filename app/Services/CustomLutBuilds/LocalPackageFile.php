<?php

namespace App\Services\CustomLutBuilds;

use App\Enums\CustomLutBuildFileKind;

final readonly class LocalPackageFile
{
    public function __construct(
        public CustomLutBuildFileKind $kind,
        public string $localPath,
        public string $relativePackagePath,
        public string $safeDownloadName,
        public string $mimeType,
        public int $sortOrder,
    ) {}

    public function sizeBytes(): int
    {
        $size = filesize($this->localPath);

        return $size === false ? 0 : $size;
    }

    public function sha256(): string
    {
        return hash_file('sha256', $this->localPath) ?: '';
    }
}
