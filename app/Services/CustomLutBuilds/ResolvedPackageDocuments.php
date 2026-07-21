<?php

namespace App\Services\CustomLutBuilds;

final readonly class ResolvedPackageDocuments
{
    public function __construct(
        public PackageDocumentSnapshot $license,
        public PackageDocumentSnapshot $guide,
    ) {}

    public function containsDraftDocuments(): bool
    {
        return $this->license->isDraft() || $this->guide->isDraft();
    }

    public function documentsAllowSale(): bool
    {
        return $this->license->mayBeUsedForSaleBuild() && $this->guide->mayBeUsedForSaleBuild();
    }
}
