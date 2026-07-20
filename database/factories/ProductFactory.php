<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->bothify('LUT Product ###');

        return [
            'type' => ProductType::SingleLut,
            'status' => ProductStatus::Draft,
            'name' => Str::headline($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'sku' => fake()->boolean(70) ? fake()->unique()->bothify('LUT-####') : null,
            'short_description' => fake()->sentence(8),
            'description' => fake()->optional()->paragraph(),
            'price_cents' => fake()->numberBetween(900, 9900),
            'currency' => 'EUR',
            'is_featured' => false,
            'is_testable' => false,
            'published_at' => null,
            'meta_title' => fake()->optional()->sentence(4),
            'meta_description' => fake()->optional()->sentence(10),
        ];
    }

    public function singleLut(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::SingleLut,
            'price_cents' => $attributes['price_cents'] ?? 1999,
        ]);
    }

    public function freeLut(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::FreeLut,
            'price_cents' => 0,
        ]);
    }

    public function bundle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Bundle,
            'price_cents' => $attributes['price_cents'] ?? 4999,
            'is_testable' => false,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function testable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_testable' => true,
        ]);
    }
}
