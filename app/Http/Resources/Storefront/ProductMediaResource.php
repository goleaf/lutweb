<?php

namespace App\Http\Resources\Storefront;

use App\Models\ProductMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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

        if (! $media instanceof ProductMedia || $media->disk !== 'public') {
            return [];
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
