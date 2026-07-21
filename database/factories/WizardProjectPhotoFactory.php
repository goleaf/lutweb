<?php

namespace Database\Factories;

use App\Enums\WizardPhotoStatus;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WizardProjectPhoto>
 */
class WizardProjectPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wizard_project_id' => WizardProject::factory(),
            'status' => WizardPhotoStatus::Queued,
            'disk' => 'private',
            'raw_path' => null,
            'preview_path' => null,
            'original_name' => 'photo.jpg',
            'original_mime_type' => 'image/jpeg',
            'original_size_bytes' => 1024,
            'original_width' => 640,
            'original_height' => 640,
            'preview_mime_type' => null,
            'preview_width' => null,
            'preview_height' => null,
            'sort_order' => 1,
            'failure_code' => null,
            'failure_message' => null,
            'expires_at' => now()->addMinutes((int) config('lut-wizard.photo_expiration_minutes', 60)),
            'completed_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WizardPhotoStatus::Ready,
            'preview_path' => 'custom-lut-projects/1/project/photos/photo/preview.webp',
            'preview_mime_type' => 'image/webp',
            'preview_width' => 640,
            'preview_height' => 640,
            'completed_at' => now(),
        ]);
    }
}
