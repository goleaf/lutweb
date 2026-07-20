<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Seed catalog reference data.
     */
    public function run(): void
    {
        foreach ($this->categories() as $sortOrder => $name) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        foreach ($this->tags() as $name) {
            Tag::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        foreach ($this->compatibleSoftware() as $sortOrder => $name) {
            CompatibleSoftware::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'website_url' => null,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        return [
            'Cinematic',
            'Portrait',
            'Travel',
            'Street',
            'Wedding',
            'Warm',
            'Cool',
            'Moody',
            'Vintage',
            'Pastel',
            'Bright & Clean',
            'Dark & Dramatic',
            'Teal & Orange',
            'Black & White',
        ];
    }

    /**
     * @return list<string>
     */
    private function tags(): array
    {
        return [
            'For Portraits',
            'For Landscapes',
            'For Travel',
            'For Instagram',
            'For Weddings',
            'Skin Friendly',
            'High Contrast',
            'Low Contrast',
            'Soft',
            'Matte',
            'Film Look',
            'Golden',
            'Desaturated',
            'Natural',
            'Dramatic',
        ];
    }

    /**
     * @return list<string>
     */
    private function compatibleSoftware(): array
    {
        return [
            'Adobe Photoshop',
            'Adobe Premiere Pro',
            'DaVinci Resolve',
            'Final Cut Pro',
            'Affinity Photo',
        ];
    }
}
