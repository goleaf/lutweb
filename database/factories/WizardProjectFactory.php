<?php

namespace Database\Factories;

use App\Enums\LutTransformVersion;
use App\Enums\WizardProjectStatus;
use App\Models\User;
use App\Models\WizardProject;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WizardProject>
 */
class WizardProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $parameters = LutTransformParameters::neutral();

        return [
            'user_id' => User::factory(),
            'wizard_style_id' => null,
            'name' => 'Untitled LUT',
            'status' => WizardProjectStatus::Draft,
            'transform_version' => LutTransformVersion::V1,
            'style_name_snapshot' => null,
            'style_snapshot' => null,
            'parameters' => $parameters->toArray(),
            'parameters_hash' => $parameters->hash(),
            'project_seed' => hash('sha256', fake()->uuid()),
            'revision' => 1,
            'variation_generation' => 0,
            'last_mutation_id' => null,
            'last_autosaved_at' => null,
            'expires_at' => now()->addDays((int) config('lut-wizard.project_expiration_days', 30)),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
