<?php

namespace App\Http\Resources\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\StorefrontMedia\StorefrontResponsiveImageFactory;
use App\Support\Catalog\EurMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource;

        if (! $product instanceof Product) {
            return [];
        }

        return [
            'id' => $product->id,
            'type' => $product->type->value,
            'type_label' => $product->type->label(),
            'name' => $product->name,
            'slug' => $product->slug,
            'url' => route('shop.show', $product->slug),
            'short_description' => $product->short_description,
            'formatted_price' => $product->isFree() ? 'Free' : '€'.EurMoney::formatCents($product->price_cents),
            'is_free' => $product->isFree(),
            'currency' => $product->currency,
            'is_featured' => $product->is_featured,
            'cover' => $this->media($product->coverMedia),
            'categories' => $product->categories
                ->take(2)
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'url' => route('categories.show', $category->slug),
                    'products_count' => null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function media(?ProductMedia $media): ?array
    {
        if ($media === null) {
            return null;
        }

        $image = app(StorefrontResponsiveImageFactory::class)->forMedia($media);

        return [
            'id' => $media->id,
            'kind' => $media->kind->value,
            'url' => $image['fallback_jpeg_url'] ?? null,
            'alt_text' => $media->alt_text,
            'width' => $media->width,
            'height' => $media->height,
            'image' => $image,
        ];
    }
}
