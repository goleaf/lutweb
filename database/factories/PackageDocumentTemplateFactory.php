<?php

namespace Database\Factories;

use App\Enums\PackageDocumentKind;
use App\Enums\PackageDocumentStatus;
use App\Models\PackageDocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageDocumentTemplate>
 */
class PackageDocumentTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => PackageDocumentKind::License,
            'status' => PackageDocumentStatus::Draft,
            'version' => 'draft-'.$this->faker->unique()->bothify('??-###'),
            'title' => 'Draft License',
            'body' => "DRAFT PLACEHOLDER - NOT FOR PRODUCTION SALE\n\nFinal licensing text must be reviewed before launch.",
            'is_current' => false,
            'activated_at' => null,
        ];
    }

    public function current(PackageDocumentKind $kind = PackageDocumentKind::License): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => $kind,
            'is_current' => true,
        ]);
    }

    public function active(PackageDocumentKind $kind = PackageDocumentKind::License): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => $kind,
            'status' => PackageDocumentStatus::Active,
            'version' => 'v'.$this->faker->unique()->numberBetween(1, 999),
            'is_current' => true,
            'activated_at' => now(),
        ]);
    }
}
