<?php

namespace Database\Factories;

use App\Enums\LutTestStatus;
use App\Models\LutTestUpload;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LutTestUpload>
 */
class LutTestUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'product_version_id' => null,
            'product_file_id' => null,
            'status' => LutTestStatus::Queued,
            'disk' => 'private',
            'raw_path' => null,
            'normalized_path' => null,
            'before_preview_path' => null,
            'after_preview_path' => null,
            'original_name' => 'photo.jpg',
            'original_mime_type' => 'image/jpeg',
            'original_size_bytes' => fake()->numberBetween(10_000, 500_000),
            'original_width' => 640,
            'original_height' => 640,
            'preview_mime_type' => null,
            'preview_width' => null,
            'preview_height' => null,
            'failure_code' => null,
            'failure_message' => null,
            'expires_at' => now()->addHour(),
            'completed_at' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LutTestStatus::Processing,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LutTestStatus::Ready,
            'before_preview_path' => 'lut-tests/1/test/before.webp',
            'after_preview_path' => 'lut-tests/1/test/after.webp',
            'preview_mime_type' => 'image/webp',
            'preview_width' => 640,
            'preview_height' => 640,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LutTestStatus::Failed,
            'failure_code' => 'processing_failed',
            'failure_message' => 'We could not process this image.',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LutTestStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
