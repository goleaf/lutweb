<?php

namespace Database\Factories;

use App\Enums\CubeGeneratorVersion;
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
            'parameters' => $parameters->toArray(),
            'parameters_hash' => $parameters->hash(),
            'build_fingerprint' => hash('sha256', 'build-'.$buildId),
            'build_request_id' => fake()->uuid(),
            'disk' => 'private',
            'transform_version' => LutTransformVersion::V1->value,
            'generator_version' => CubeGeneratorVersion::V1->value,
            'package_schema_version' => 'lut-web-custom-package-v1',
            'status' => CustomLutBuildStatus::Ready,
            'sale_ready' => true,
            'contains_draft_documents' => false,
            'is_current' => true,
            'zip_validation_completed' => true,
            'parity_validation_passed' => true,
            'ffmpeg_validation_passed' => true,
            'parity_mean_error_millionths' => 0,
            'parity_p95_error_millionths' => 0,
            'parity_p99_error_millionths' => 0,
            'parity_max_error_millionths' => 0,
            'license_version' => 'license-v1',
            'license_template_hash' => hash('sha256', 'license-v1'),
            'guide_version' => 'guide-v1',
            'guide_template_hash' => hash('sha256', 'guide-v1'),
            'zip_size_bytes' => 1024,
            'zip_sha256' => hash('sha256', 'zip-'.$buildId),
            'uncompressed_size_bytes' => 2048,
            'failure_code' => null,
            'failure_message' => null,
            'license_document_snapshot' => [
                'id' => (string) Str::ulid(),
                'kind' => 'license',
                'status' => 'active',
                'version' => 'license-v1',
                'title' => 'License',
                'body' => 'Final license terms.',
                'is_current' => true,
                'content_hash' => hash('sha256', 'license-v1'),
            ],
            'guide_document_snapshot' => [
                'id' => (string) Str::ulid(),
                'kind' => 'installation_guide',
                'status' => 'active',
                'version' => 'guide-v1',
                'title' => 'Installation Guide',
                'body' => 'Install the CUBE file in your supported host application.',
                'is_current' => true,
                'content_hash' => hash('sha256', 'guide-v1'),
            ],
            'started_at' => now(),
            'completed_at' => now(),
            'superseded_at' => null,
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

    public function queued(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CustomLutBuildStatus::Queued,
            'sale_ready' => false,
            'is_current' => false,
            'prepared_at' => null,
            'completed_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CustomLutBuildStatus::Processing,
            'sale_ready' => false,
            'is_current' => false,
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    public function draftDocuments(): static
    {
        return $this->state(fn (array $attributes): array => [
            'contains_draft_documents' => true,
            'sale_ready' => false,
            'license_version' => 'draft-license-v1',
            'guide_version' => 'draft-guide-v1',
        ]);
    }
}
