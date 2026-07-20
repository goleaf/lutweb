<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\BundleItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateProductAsDraft
{
    public function handle(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $copy = Product::query()->create([
                'type' => $product->type,
                'status' => ProductStatus::Draft,
                'name' => $product->name.' Copy',
                'slug' => $this->uniqueSlug($product->slug.'-copy'),
                'sku' => null,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'price_cents' => $product->price_cents,
                'currency' => $product->currency,
                'is_featured' => false,
                'published_at' => null,
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
            ]);

            $copy->categories()->sync($product->categories()->pluck('categories.id')->all());
            $copy->tags()->sync($product->tags()->pluck('tags.id')->all());
            $copy->compatibleSoftware()->sync(
                $product->compatibleSoftware()->pluck('compatible_software.id')->all(),
            );

            $product->bundleItems()
                ->orderBy('sort_order')
                ->get()
                ->each(fn (BundleItem $item) => $copy->bundleItems()->create([
                    'product_id' => $item->product_id,
                    'sort_order' => $item->sort_order,
                ]));

            return $copy->refresh();
        });
    }

    protected function uniqueSlug(string $baseSlug): string
    {
        $baseSlug = Str::slug($baseSlug);
        $slug = $baseSlug;
        $suffix = 2;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
