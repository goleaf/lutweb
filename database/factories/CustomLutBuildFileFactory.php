<?php

namespace Database\Factories;

use App\Enums\CustomLutBuildFileKind;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomLutBuildFile>
 */
class CustomLutBuildFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_lut_build_id' => CustomLutBuild::factory(),
            'kind' => CustomLutBuildFileKind::PackageZip,
            'disk' => 'private',
            'path' => 'custom-lut-builds/'.fake()->uuid().'/package.zip',
            'original_name' => 'package.zip',
            'mime_type' => 'application/zip',
            'size_bytes' => fake()->numberBetween(1024, 1024 * 1024),
            'sha256' => hash('sha256', fake()->uuid()),
            'sort_order' => 0,
        ];
    }

    public function packageZip(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => CustomLutBuildFileKind::PackageZip,
            'disk' => 'private',
            'original_name' => 'package.zip',
            'mime_type' => 'application/zip',
        ]);
    }
}
