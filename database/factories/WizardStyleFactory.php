<?php

namespace Database\Factories;

use App\Enums\LutTransformVersion;
use App\Models\WizardStyle;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WizardStyle>
 */
class WizardStyleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = LutTransformParameters::neutral()->toArray();
        $name = fake()->words(2, true);
        $slugTitle = fake()->unique()->words(3, true);

        return [
            'name' => is_string($name) ? $name : implode(' ', $name),
            'slug' => Str::slug(is_string($slugTitle) ? $slugTitle : implode(' ', $slugTitle)),
            'description' => fake()->sentence(),
            'transform_version' => LutTransformVersion::V1,
            'base_parameters' => $base,
            'minimum_parameters' => $this->minimumParameters($base),
            'maximum_parameters' => $this->maximumParameters($base),
            'variation_amounts' => array_fill_keys(LutTransformParameters::keys(), 80),
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * @param  array<string, int>  $base
     * @return array<string, int>
     */
    private function minimumParameters(array $base): array
    {
        $minimum = [];

        foreach ($base as $key => $value) {
            $minimum[$key] = LutTransformParameters::isHueKey($key)
                ? LutTransformParameters::minimum($key)
                : max(LutTransformParameters::minimum($key), $value - 300);
        }

        return $minimum;
    }

    /**
     * @param  array<string, int>  $base
     * @return array<string, int>
     */
    private function maximumParameters(array $base): array
    {
        $maximum = [];

        foreach ($base as $key => $value) {
            $maximum[$key] = LutTransformParameters::isHueKey($key)
                ? LutTransformParameters::maximum($key)
                : min(LutTransformParameters::maximum($key), $value + 300);
        }

        return $maximum;
    }
}
