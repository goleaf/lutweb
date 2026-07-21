<?php

namespace App\Services\Checkout;

use App\Enums\DigitalAssetKind;

final readonly class ResolvedEntitlementPackage
{
    public function __construct(
        public DigitalAssetKind $kind,
        public string $disk,
        public string $path,
        public string $downloadName,
        public int $sizeBytes,
    ) {}
}
