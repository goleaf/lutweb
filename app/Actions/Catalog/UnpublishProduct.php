<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;

class UnpublishProduct
{
    public function handle(Product $product): Product
    {
        $product->forceFill([
            'status' => ProductStatus::Draft,
        ])->save();

        return $product->refresh();
    }
}
