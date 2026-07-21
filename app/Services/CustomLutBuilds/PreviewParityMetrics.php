<?php

namespace App\Services\CustomLutBuilds;

final readonly class PreviewParityMetrics
{
    public function __construct(
        public int $meanMillionths,
        public int $p95Millionths,
        public int $p99Millionths,
        public int $maxMillionths,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'mean_millionths' => $this->meanMillionths,
            'p95_millionths' => $this->p95Millionths,
            'p99_millionths' => $this->p99Millionths,
            'max_millionths' => $this->maxMillionths,
        ];
    }
}
