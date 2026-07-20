<?php

namespace Database\Factories;

use App\Enums\ProductFileKind;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFile>
 */
class ProductFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_version_id' => ProductVersion::factory(),
            'kind' => ProductFileKind::PackageZip,
            'disk' => 'private',
            'path' => 'products/releases/'.fake()->uuid().'.zip',
            'original_name' => 'package.zip',
            'mime_type' => 'application/zip',
            'size_bytes' => fake()->numberBetween(1024, 1024 * 1024),
            'sha256' => hash('sha256', fake()->uuid()),
            'sort_order' => 0,
        ];
    }

    public function packageZip(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ProductFileKind::PackageZip,
            'path' => 'products/releases/'.fake()->uuid().'.zip',
            'original_name' => 'package.zip',
            'mime_type' => 'application/zip',
        ]);
    }

    public function cube33(): static
    {
        return $this->cube(ProductFileKind::Cube33, 'cube-33.cube');
    }

    public function cube65(): static
    {
        return $this->cube(ProductFileKind::Cube65, 'cube-65.cube');
    }

    public function cube17(): static
    {
        return $this->cube(ProductFileKind::Cube17, 'cube-17.cube');
    }

    public function sourceCube(): static
    {
        return $this->cube(ProductFileKind::SourceCube, 'source.cube');
    }

    private function cube(ProductFileKind $kind, string $originalName): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => $kind,
            'disk' => 'private',
            'path' => 'products/luts/'.fake()->uuid().'.cube',
            'original_name' => $originalName,
            'mime_type' => 'text/plain',
        ]);
    }
}
