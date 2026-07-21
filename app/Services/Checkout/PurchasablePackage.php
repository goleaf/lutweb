<?php

namespace App\Services\Checkout;

use App\Models\ProductFile;
use App\Models\ProductVersion;

final readonly class PurchasablePackage
{
    public function __construct(
        public ProductVersion $version,
        public ProductFile $file,
    ) {}
}
