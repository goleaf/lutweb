<?php

namespace App\Actions\StorefrontMedia;

use App\Enums\StorefrontImageStatus;
use App\Models\Product;
use App\Models\ProductExample;

class MarkProductExamplesStale
{
    public function forProduct(Product $product): int
    {
        $count = ProductExample::query()
            ->where(function ($query) use ($product): void {
                $query->where('product_id', $product->id)
                    ->orWhere('preview_product_id', $product->id);
            })
            ->where('processing_status', StorefrontImageStatus::Ready->value)
            ->update([
                'processing_status' => StorefrontImageStatus::Stale->value,
                'stale_at' => now(),
                'updated_at' => now(),
            ]);

        return (int) $count;
    }
}
