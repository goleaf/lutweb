<?php

namespace Database\Factories;

use App\Enums\ProductVersionStatus;
use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVersion>
 */
class ProductVersionFactory extends Factory
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
            'version' => fake()->unique()->numerify('1.#.#'),
            'status' => ProductVersionStatus::Draft,
            'is_current' => false,
            'released_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductVersionStatus::Draft,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductVersionStatus::Ready,
            'released_at' => now(),
        ]);
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
        ]);
    }
}
