<?php

namespace Database\Factories;

use App\Models\CustomLutCommerceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomLutCommerceSetting>
 */
class CustomLutCommerceSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => 'custom_lut',
            'is_enabled' => false,
            'price_cents' => 0,
            'currency' => 'EUR',
            'version' => 1,
            'updated_by' => null,
        ];
    }
}
