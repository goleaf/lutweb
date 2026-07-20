<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetCurrentProductVersion
{
    public function handle(Product $product, ProductVersion $version): ProductVersion
    {
        if ($version->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'version' => 'The selected version does not belong to this product.',
            ]);
        }

        return DB::transaction(function () use ($product, $version): ProductVersion {
            ProductVersion::query()
                ->whereBelongsTo($product)
                ->lockForUpdate()
                ->update(['is_current' => false]);

            $version->forceFill(['is_current' => true])->save();

            return $version->refresh();
        });
    }
}
