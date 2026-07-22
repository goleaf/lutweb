<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StorefrontPreviewSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a minimal public catalog without accounts, orders, or fake files.
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        $travelCategory = Category::query()->where('slug', 'travel')->firstOrFail();
        $tagIds = Tag::query()
            ->whereIn('slug', ['film-look', 'for-travel', 'golden', 'natural'])
            ->pluck('id')
            ->all();
        $softwareIds = CompatibleSoftware::query()
            ->whereIn('slug', [
                'adobe-photoshop',
                'adobe-premiere-pro',
                'affinity-photo',
                'davinci-resolve',
                'final-cut-pro',
            ])
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($softwareIds, $tagIds, $travelCategory): void {
            foreach ($this->products() as $attributes) {
                $sku = $attributes['sku'];
                unset($attributes['sku']);

                $product = Product::query()
                    ->withTrashed()
                    ->updateOrCreate(['sku' => $sku], $attributes);

                if ($product->trashed()) {
                    $product->restore();
                }

                $product->categories()->syncWithoutDetaching([$travelCategory->id]);
                $product->tags()->syncWithoutDetaching($tagIds);
                $product->compatibleSoftware()->syncWithoutDetaching($softwareIds);
            }
        });

        $this->command?->warn(
            'Storefront preview products contain no downloadable files and must be replaced before accepting real orders.',
        );
    }

    /**
     * @return list<array{
     *     type: ProductType,
     *     status: ProductStatus,
     *     name: string,
     *     slug: string,
     *     sku: string,
     *     short_description: string,
     *     description: string,
     *     price_cents: int,
     *     currency: string,
     *     is_featured: bool,
     *     is_testable: bool,
     *     published_at: string,
     *     meta_title: string,
     *     meta_description: string
     * }>
     */
    private function products(): array
    {
        return [
            [
                'type' => ProductType::SingleLut,
                'status' => ProductStatus::Published,
                'name' => 'Alpine Morning Travel LUT',
                'slug' => 'alpine-morning-travel-lut',
                'sku' => 'PREVIEW-TRAVEL-001',
                'short_description' => 'Clean mountain light with crisp blues, soft greens, and natural skin tones.',
                'description' => 'A balanced travel look for bright alpine landscapes, hiking films, and outdoor portraits.',
                'price_cents' => 1900,
                'currency' => 'EUR',
                'is_featured' => true,
                'is_testable' => false,
                'published_at' => '2026-07-01 09:00:00',
                'meta_title' => 'Alpine Morning Travel LUT',
                'meta_description' => 'A clean travel LUT for mountain landscapes and natural outdoor footage.',
            ],
            [
                'type' => ProductType::SingleLut,
                'status' => ProductStatus::Published,
                'name' => 'Coastal Film Travel LUT',
                'slug' => 'coastal-film-travel-lut',
                'sku' => 'PREVIEW-TRAVEL-002',
                'short_description' => 'A relaxed film palette for sea air, pale skies, and sunlit coastlines.',
                'description' => 'Muted highlights and gentle contrast give coastal travel footage a timeless film character.',
                'price_cents' => 2400,
                'currency' => 'EUR',
                'is_featured' => false,
                'is_testable' => false,
                'published_at' => '2026-07-02 09:00:00',
                'meta_title' => 'Coastal Film Travel LUT',
                'meta_description' => 'A soft film-inspired LUT for coastal travel photos and video.',
            ],
            [
                'type' => ProductType::SingleLut,
                'status' => ProductStatus::Published,
                'name' => 'Golden City Travel LUT',
                'slug' => 'golden-city-travel-lut',
                'sku' => 'PREVIEW-TRAVEL-003',
                'short_description' => 'Warm evening color for architecture, street scenes, and golden-hour portraits.',
                'description' => 'Warm highlights and controlled shadows bring depth to city breaks and architectural stories.',
                'price_cents' => 2100,
                'currency' => 'EUR',
                'is_featured' => true,
                'is_testable' => false,
                'published_at' => '2026-07-03 09:00:00',
                'meta_title' => 'Golden City Travel LUT',
                'meta_description' => 'A warm golden-hour LUT for city travel and architecture.',
            ],
            [
                'type' => ProductType::SingleLut,
                'status' => ProductStatus::Published,
                'name' => 'Nordic Air Travel LUT',
                'slug' => 'nordic-air-travel-lut',
                'sku' => 'PREVIEW-TRAVEL-004',
                'short_description' => 'Cool, airy color with clean whites for northern landscapes and modern interiors.',
                'description' => 'A restrained Nordic palette designed for overcast scenery, minimalist spaces, and calm travel films.',
                'price_cents' => 1800,
                'currency' => 'EUR',
                'is_featured' => false,
                'is_testable' => false,
                'published_at' => '2026-07-04 09:00:00',
                'meta_title' => 'Nordic Air Travel LUT',
                'meta_description' => 'A clean and airy LUT for Nordic travel scenery and interiors.',
            ],
            [
                'type' => ProductType::SingleLut,
                'status' => ProductStatus::Published,
                'name' => 'Night Market Travel LUT',
                'slug' => 'night-market-travel-lut',
                'sku' => 'PREVIEW-TRAVEL-005',
                'short_description' => 'Rich neon color with deep contrast for markets, nightlife, and rainy streets.',
                'description' => 'Balanced neon tones preserve colorful signs while giving night travel footage cinematic depth.',
                'price_cents' => 2200,
                'currency' => 'EUR',
                'is_featured' => false,
                'is_testable' => false,
                'published_at' => '2026-07-05 09:00:00',
                'meta_title' => 'Night Market Travel LUT',
                'meta_description' => 'A cinematic neon LUT for night markets and urban travel.',
            ],
            [
                'type' => ProductType::SingleLut,
                'status' => ProductStatus::Published,
                'name' => 'Desert Road Travel LUT',
                'slug' => 'desert-road-travel-lut',
                'sku' => 'PREVIEW-TRAVEL-006',
                'short_description' => 'Earthy warmth and open-sky contrast for road trips and desert landscapes.',
                'description' => 'Sand, stone, and blue skies stay detailed with a warm cinematic finish for road-trip stories.',
                'price_cents' => 2000,
                'currency' => 'EUR',
                'is_featured' => false,
                'is_testable' => false,
                'published_at' => '2026-07-06 09:00:00',
                'meta_title' => 'Desert Road Travel LUT',
                'meta_description' => 'A warm earthy LUT for desert landscapes and travel road trips.',
            ],
        ];
    }
}
