<?php

namespace App\Services\LutTester;

final readonly class CubeInspectionResult
{
    /**
     * @param  array<int, float>|null  $domainMin
     * @param  array<int, float>|null  $domainMax
     */
    public function __construct(
        public int $size,
        public int $rows,
        public ?string $title = null,
        public ?array $domainMin = null,
        public ?array $domainMax = null,
    ) {}
}
