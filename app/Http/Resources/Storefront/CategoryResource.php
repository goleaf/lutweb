<?php

namespace App\Http\Resources\Storefront;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->resource;

        if (! $category instanceof Category) {
            return [];
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'url' => route('categories.show', $category->slug),
            'products_count' => (int) ($category->published_products_count ?? $category->products_count ?? 0),
        ];
    }
}
