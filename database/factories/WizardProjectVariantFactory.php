<?php

namespace Database\Factories;

use App\Enums\WizardVariationMode;
use App\Models\WizardProject;
use App\Models\WizardProjectVariant;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WizardProjectVariant>
 */
class WizardProjectVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $parameters = LutTransformParameters::neutral()->withChanges([
            'contrast' => fake()->numberBetween(-100, 100),
        ]);

        return [
            'wizard_project_id' => WizardProject::factory(),
            'generation' => 1,
            'position' => 1,
            'mode' => WizardVariationMode::Fresh,
            'seed' => hash('sha256', fake()->uuid()),
            'parameters' => $parameters->toArray(),
            'parameters_hash' => $parameters->hash(),
            'selected_at' => null,
        ];
    }
}
