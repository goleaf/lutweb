<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductExample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductExample>
 */
class ProductExampleFactory extends Factory
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
            'title' => fake()->optional()->sentence(3),
            'before_disk' => 'public',
            'before_path' => 'products/examples/'.fake()->uuid().'-before.jpg',
            'before_original_name' => 'before.jpg',
            'before_alt_text' => 'Before applying the LUT',
            'after_disk' => 'public',
            'after_path' => 'products/examples/'.fake()->uuid().'-after.jpg',
            'after_original_name' => 'after.jpg',
            'after_alt_text' => 'After applying the LUT',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
