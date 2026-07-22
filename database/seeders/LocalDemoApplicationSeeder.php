<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalDemoApplicationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensureLocalOrTesting();

        $this->call([
            CustomLutCommerceSettingsSeeder::class,
            CatalogSeeder::class,
            WizardStyleSeeder::class,
            PackageDocumentTemplateSeeder::class,
            LocalDemoUserSeeder::class,
            LocalDemoProductSeeder::class,
            LocalDemoCommerceSeeder::class,
            LocalDemoCustomLutSeeder::class,
            LocalDemoOperationsSeeder::class,
        ]);

        $this->command->warn('Local demo application data is temporary and must not be used as production launch data.');
    }

    private function ensureLocalOrTesting(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo application data may only be seeded in local or testing environments.');
        }
    }
}
