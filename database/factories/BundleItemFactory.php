<?php

namespace Database\Factories;

use App\Models\BundleItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BundleItem>
 */
class BundleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bundle_id' => Product::factory()->bundle(),
            'product_id' => Product::factory()->singleLut(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
