<?php

namespace Database\Factories;

use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontMediaPipelineVersion;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\StorefrontImageVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductExample>
 */
class ProductExampleFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (ProductExample $example): void {
            StorefrontImageVariant::factory()
                ->for($example, 'imageable')
                ->createMany([
                    ['format' => 'jpeg', 'mime_type' => 'image/jpeg', 'role' => 'before', 'path' => 'storefront/testing/example-'.$example->id.'-before-768.jpeg', 'width' => 768, 'height' => 576],
                    ['format' => 'webp', 'mime_type' => 'image/webp', 'role' => 'before', 'path' => 'storefront/testing/example-'.$example->id.'-before-768.webp', 'width' => 768, 'height' => 576],
                    ['format' => 'jpeg', 'mime_type' => 'image/jpeg', 'role' => 'after', 'path' => 'storefront/testing/example-'.$example->id.'-after-768.jpeg', 'width' => 768, 'height' => 576],
                    ['format' => 'webp', 'mime_type' => 'image/webp', 'role' => 'after', 'path' => 'storefront/testing/example-'.$example->id.'-after-768.webp', 'width' => 768, 'height' => 576],
                ]);
        });
    }

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
            'processing_status' => StorefrontImageStatus::Ready,
            'pipeline_version' => StorefrontMediaPipelineVersion::V1,
            'rights_confirmed_at' => now(),
            'source_width' => 1600,
            'source_height' => 1200,
            'source_credit_is_public' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
