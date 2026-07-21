<?php

namespace Database\Factories;

use App\Enums\ProductMediaKind;
use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontMediaPipelineVersion;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\StorefrontImageVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (ProductMedia $media): void {
            StorefrontImageVariant::factory()
                ->for($media, 'imageable')
                ->createMany([
                    ['format' => 'jpeg', 'mime_type' => 'image/jpeg', 'role' => 'media', 'path' => 'storefront/testing/media-'.$media->id.'-768.jpeg', 'width' => 768, 'height' => 576],
                    ['format' => 'webp', 'mime_type' => 'image/webp', 'role' => 'media', 'path' => 'storefront/testing/media-'.$media->id.'-768.webp', 'width' => 768, 'height' => 576],
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
            'kind' => ProductMediaKind::Gallery,
            'disk' => 'public',
            'path' => 'products/media/'.fake()->uuid().'.jpg',
            'original_name' => 'preview.jpg',
            'alt_text' => fake()->sentence(5),
            'width' => 1600,
            'height' => 1200,
            'sort_order' => fake()->numberBetween(0, 100),
            'processing_status' => StorefrontImageStatus::Ready,
            'pipeline_version' => StorefrontMediaPipelineVersion::V1,
            'rights_confirmed_at' => now(),
            'source_credit_is_public' => false,
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
