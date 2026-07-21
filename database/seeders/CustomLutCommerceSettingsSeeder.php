<?php

namespace Database\Seeders;

use App\Models\CustomLutCommerceSetting;
use Illuminate\Database\Seeder;

class CustomLutCommerceSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CustomLutCommerceSetting::query()->firstOrCreate(
            ['scope' => CustomLutCommerceSetting::Scope],
            [
                'is_enabled' => false,
                'price_cents' => 0,
                'currency' => 'EUR',
                'version' => 1,
                'updated_by' => null,
            ],
        );
    }
}
