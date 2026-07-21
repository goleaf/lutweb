<?php

namespace App\Services\CustomLutBuilds;

final readonly class GeneratedCustomLutPackage
{
    /**
     * @param  list<LocalPackageFile>  $files
     */
    public function __construct(
        public array $files,
        public LocalPackageFile $zip,
        public int $uncompressedSizeBytes,
        public PreviewParityMetrics $parityMetrics,
        public ResolvedPackageDocuments $documents,
    ) {}
}
