<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;

class ArchiveProduct
{
    public function handle(Product $product): Product
    {
        $product->forceFill([
            'status' => ProductStatus::Archived,
        ])->save();

        return $product->refresh();
    }
}
