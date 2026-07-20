<?php

namespace Database\Factories;

use App\Enums\ProductMediaKind;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'kind' => ProductMediaKind::Gallery,
            'disk' => 'public',
            'path' => 'products/media/'.fake()->uuid().'.jpg',
            'original_name' => 'preview.jpg',
            'alt_text' => fake()->sentence(5),
            'width' => 1600,
            'height' => 1200,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function cover(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ProductMediaKind::Cover,
            'sort_order' => 0,
        ]);
    }
}
