<?php

namespace App\Http\Resources\Storefront;

use App\Models\ProductMedia;
use App\Services\StorefrontMedia\StorefrontResponsiveImageFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductMediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->resource;

        if (! $media instanceof ProductMedia) {
            return [];
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
            'processing_status' => $media->processing_status->value,
        ];
    }
}
