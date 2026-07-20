<?php

namespace App\Services\LutTester;

use App\Models\ProductFile;
use App\Models\ProductVersion;

final readonly class ResolvedPreviewLut
{
    public function __construct(
        public ProductVersion $version,
        public ProductFile $file,
        public string $absolutePath,
        public CubeInspectionResult $inspection,
    ) {}
}
