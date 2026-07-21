<?php

namespace Database\Factories;

use App\Enums\CustomLutBuildStatus;
use App\Enums\LutTransformVersion;
use App\Models\CustomLutBuild;
use App\Models\User;
use App\Models\WizardProject;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomLutBuild>
 */
class CustomLutBuildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $parameters = LutTransformParameters::neutral();
        $buildId = (string) Str::ulid();

        return [
            'id' => $buildId,
            'user_id' => User::factory(),
            'wizard_project_id' => WizardProject::factory(),
            'project_name_snapshot' => fake()->words(3, true),
            'style_name_snapshot' => null,
            'package_stem' => 'custom-lut-'.$buildId,
            'project_revision' => 1,
            'parameters_hash' => $parameters->hash(),
            'build_fingerprint' => hash('sha256', 'build-'.$buildId),
            'transform_version' => LutTransformVersion::V1->value,
            'generator_version' => 'v1',
            'package_schema_version' => 'v1',
            'status' => CustomLutBuildStatus::Ready,
            'sale_ready' => true,
            'contains_draft_documents' => false,
            'is_current' => true,
            'zip_validation_completed' => true,
            'parity_validation_passed' => true,
            'ffmpeg_validation_passed' => true,
            'license_version' => 'license-v1',
            'license_template_hash' => hash('sha256', 'license-v1'),
            'guide_version' => 'guide-v1',
            'guide_template_hash' => hash('sha256', 'guide-v1'),
            'prepared_at' => now(),
            'expires_at' => now()->addDays(30),
            'locked_at' => null,
            'first_ordered_at' => null,
            'purchased_at' => null,
        ];
    }

    public function saleReady(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CustomLutBuildStatus::Ready,
            'sale_ready' => true,
            'contains_draft_documents' => false,
            'is_current' => true,
            'zip_validation_completed' => true,
            'parity_validation_passed' => true,
            'ffmpeg_validation_passed' => true,
        ]);
    }
}
