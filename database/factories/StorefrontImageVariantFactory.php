<?php

namespace Database\Factories;

use App\Enums\StorefrontImageFormat;
use App\Enums\StorefrontImageVariantRole;
use App\Models\ProductMedia;
use App\Models\StorefrontImageVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorefrontImageVariant>
 */
class StorefrontImageVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $width = fake()->randomElement([480, 768, 1200]);
        $height = (int) round($width * 0.75);
        $format = fake()->randomElement(StorefrontImageFormat::cases());

        return [
            'imageable_type' => ProductMedia::class,
            'imageable_id' => ProductMedia::factory(),
            'role' => StorefrontImageVariantRole::Media,
            'format' => $format,
            'disk' => 'public',
            'path' => 'storefront/'.fake()->uuid().'.'.$format->value,
            'mime_type' => $format === StorefrontImageFormat::Webp ? 'image/webp' : 'image/jpeg',
            'width' => $width,
            'height' => $height,
            'quality' => $format === StorefrontImageFormat::Webp ? 82 : 84,
            'size_bytes' => fake()->numberBetween(10_000, 500_000),
            'sha256' => hash('sha256', fake()->uuid()),
            'generated_at' => now(),
        ];
    }
}
