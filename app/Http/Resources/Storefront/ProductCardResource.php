<?php

namespace App\Http\Resources\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Support\Catalog\EurMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
                    'url' => route('categories.show', $category->slug),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, kind: string, url: string, alt_text: string, width: int|null, height: int|null}|null
     */
    private function media(?ProductMedia $media): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'id' => $media->id,
            'kind' => $media->kind->value,
            'url' => Storage::disk('public')->url($media->path),
            'alt_text' => $media->alt_text,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }
}
