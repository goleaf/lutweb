<?php

namespace App\Support\Storefront;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Str;

final class StorefrontPreviewCatalog
{
    /**
     * @var array<string, array{name: string, code: string, subject: string, tags: list<string>}>
     */
    private const PRIMARY_CATEGORIES = [
        'cinematic' => [
            'name' => 'Cinematic',
            'code' => 'CINEMATIC',
            'subject' => 'narrative scenes, short films, and dramatic sequences',
            'tags' => ['film-look', 'dramatic'],
        ],
        'portrait' => [
            'name' => 'Portrait',
            'code' => 'PORTRAIT',
            'subject' => 'portraits, editorial sessions, and natural skin tones',
            'tags' => ['for-portraits', 'skin-friendly'],
        ],
        'travel' => [
            'name' => 'Travel',
            'code' => 'TRAVEL',
            'subject' => 'destinations, landscapes, and travel stories',
            'tags' => ['for-travel', 'for-landscapes'],
        ],
        'street' => [
            'name' => 'Street',
            'code' => 'STREET',
            'subject' => 'city walks, architecture, and candid street frames',
            'tags' => ['for-instagram', 'high-contrast'],
        ],
        'wedding' => [
            'name' => 'Wedding',
            'code' => 'WEDDING',
            'subject' => 'wedding films, ceremonies, and romantic portraits',
            'tags' => ['for-weddings', 'skin-friendly'],
        ],
        'warm' => [
            'name' => 'Warm',
            'code' => 'WARM',
            'subject' => 'golden light, interiors, and sunlit lifestyle stories',
            'tags' => ['golden', 'soft'],
        ],
        'cool' => [
            'name' => 'Cool',
            'code' => 'COOL',
            'subject' => 'clean daylight, modern spaces, and winter scenery',
            'tags' => ['natural', 'low-contrast'],
        ],
        'moody' => [
            'name' => 'Moody',
            'code' => 'MOODY',
            'subject' => 'low-key portraits, atmospheric scenes, and deep shadows',
            'tags' => ['dramatic', 'desaturated'],
        ],
        'vintage' => [
            'name' => 'Vintage',
            'code' => 'VINTAGE',
            'subject' => 'nostalgic stories, analog-inspired frames, and timeless details',
            'tags' => ['film-look', 'matte'],
        ],
        'pastel' => [
            'name' => 'Pastel',
            'code' => 'PASTEL',
            'subject' => 'soft portraits, airy details, and gentle lifestyle imagery',
            'tags' => ['soft', 'low-contrast'],
        ],
    ];

    /**
     * @var list<array{name: string, character: string}>
     */
    private const PROFILES = [
        ['name' => 'Alpine Morning', 'character' => 'clear highlights, balanced contrast, and fresh natural color'],
        ['name' => 'Coastal Film', 'character' => 'soft blues, restrained highlights, and relaxed film texture'],
        ['name' => 'Golden City', 'character' => 'amber highlights, rich midtones, and controlled urban contrast'],
        ['name' => 'Nordic Air', 'character' => 'clean whites, cool air, and a calm understated palette'],
        ['name' => 'Night Market', 'character' => 'deep contrast, vivid signs, and carefully balanced night color'],
        ['name' => 'Desert Road', 'character' => 'earthy warmth, open-sky contrast, and textured neutrals'],
        ['name' => 'Amber Drift', 'character' => 'warm highlights, softened greens, and gentle shadow depth'],
        ['name' => 'Arctic Glass', 'character' => 'cool clarity, crisp whites, and polished blue shadows'],
        ['name' => 'Bronze Fade', 'character' => 'bronze warmth, lifted blacks, and a subtle matte finish'],
        ['name' => 'Cedar Matte', 'character' => 'woodland warmth, muted saturation, and tactile faded shadows'],
        ['name' => 'Chrome Mist', 'character' => 'silvery neutrals, soft contrast, and a clean modern finish'],
        ['name' => 'Coral Bloom', 'character' => 'coral highlights, flattering warmth, and lively midtone color'],
        ['name' => 'Crimson Dusk', 'character' => 'red dusk accents, dense blacks, and cinematic highlight rolloff'],
        ['name' => 'Emerald Shadow', 'character' => 'cool green shadows, restrained warmth, and rich tonal depth'],
        ['name' => 'Faded Linen', 'character' => 'low contrast, creamy whites, and delicate desaturated color'],
        ['name' => 'Golden Veil', 'character' => 'luminous gold, soft highlights, and a smooth romantic finish'],
        ['name' => 'Harbor Blue', 'character' => 'marine blues, clean contrast, and cool balanced neutrals'],
        ['name' => 'Honey Light', 'character' => 'honeyed highlights, open shadows, and welcoming natural warmth'],
        ['name' => 'Indigo Night', 'character' => 'indigo shadows, protected highlights, and nocturnal contrast'],
        ['name' => 'Ivory Soft', 'character' => 'ivory whites, gentle contrast, and refined skin-friendly color'],
        ['name' => 'Juniper Film', 'character' => 'muted greens, warm highlights, and an organic film response'],
        ['name' => 'Lavender Haze', 'character' => 'lavender shadows, soft saturation, and dreamy tonal separation'],
        ['name' => 'Moonlit Cyan', 'character' => 'cyan shadows, cool highlights, and luminous evening detail'],
        ['name' => 'Olive Story', 'character' => 'olive greens, quiet warmth, and documentary-style contrast'],
        ['name' => 'Peach Air', 'character' => 'peach highlights, airy shadows, and soft pastel color'],
        ['name' => 'Rosewood', 'character' => 'rose-tinted warmth, deep neutrals, and elegant muted contrast'],
        ['name' => 'Silver Bleach', 'character' => 'reduced saturation, bright silver detail, and firm contrast'],
        ['name' => 'Soft Sepia', 'character' => 'subtle sepia warmth, faded shadows, and nostalgic softness'],
        ['name' => 'Teal Ember', 'character' => 'teal shadows, ember highlights, and balanced cinematic contrast'],
        ['name' => 'Velvet Contrast', 'character' => 'velvety blacks, smooth highlights, and polished color density'],
    ];

    /**
     * @var array<int, array{short_description: string, description: string, price_cents: int, meta_description: string}>
     */
    private const TRAVEL_OVERRIDES = [
        1 => [
            'short_description' => 'Clean mountain light with crisp blues, soft greens, and natural skin tones.',
            'description' => 'A balanced travel look for bright alpine landscapes, hiking films, and outdoor portraits.',
            'price_cents' => 1900,
            'meta_description' => 'A clean travel LUT for mountain landscapes and natural outdoor footage.',
        ],
        2 => [
            'short_description' => 'A relaxed film palette for sea air, pale skies, and sunlit coastlines.',
            'description' => 'Muted highlights and gentle contrast give coastal travel footage a timeless film character.',
            'price_cents' => 2400,
            'meta_description' => 'A soft film-inspired LUT for coastal travel photos and video.',
        ],
        3 => [
            'short_description' => 'Warm evening color for architecture, street scenes, and golden-hour portraits.',
            'description' => 'Warm highlights and controlled shadows bring depth to city breaks and architectural stories.',
            'price_cents' => 2100,
            'meta_description' => 'A warm golden-hour LUT for city travel and architecture.',
        ],
        4 => [
            'short_description' => 'Cool, airy color with clean whites for northern landscapes and modern interiors.',
            'description' => 'A restrained Nordic palette designed for overcast scenery, minimalist spaces, and calm travel films.',
            'price_cents' => 1800,
            'meta_description' => 'A clean and airy LUT for Nordic travel scenery and interiors.',
        ],
        5 => [
            'short_description' => 'Rich neon color with deep contrast for markets, nightlife, and rainy streets.',
            'description' => 'Balanced neon tones preserve colorful signs while giving night travel footage cinematic depth.',
            'price_cents' => 2200,
            'meta_description' => 'A cinematic neon LUT for night markets and urban travel.',
        ],
        6 => [
            'short_description' => 'Earthy warmth and open-sky contrast for road trips and desert landscapes.',
            'description' => 'Sand, stone, and blue skies stay detailed with a warm cinematic finish for road-trip stories.',
            'price_cents' => 2000,
            'meta_description' => 'A warm earthy LUT for desert landscapes and travel road trips.',
        ],
    ];

    /**
     * Values are offsets from the neutral transform and are combined with each named profile.
     *
     * @var array<string, array<string, int>>
     */
    private const CATEGORY_PARAMETER_DELTAS = [
        'cinematic' => [
            'contrast' => 180,
            'saturation' => -40,
            'highlights' => -100,
            'shadows' => -60,
            'blacks' => -60,
            'shadow_strength' => 80,
            'highlight_strength' => 70,
        ],
        'portrait' => [
            'contrast' => -80,
            'tint' => 40,
            'highlights' => -120,
            'shadows' => 110,
            'whites' => 40,
            'fade' => 20,
            'shadow_strength' => 30,
            'highlight_strength' => 80,
        ],
        'travel' => [
            'contrast' => 50,
            'vibrance' => 100,
            'highlights' => -80,
            'shadows' => 60,
            'shadow_strength' => 50,
            'highlight_strength' => 50,
        ],
        'street' => [
            'contrast' => 150,
            'saturation' => -30,
            'shadows' => -50,
            'blacks' => -60,
            'shadow_strength' => 90,
            'highlight_strength' => 50,
        ],
        'wedding' => [
            'exposure' => 15,
            'contrast' => -100,
            'temperature' => 60,
            'saturation' => -20,
            'highlights' => -150,
            'shadows' => 120,
            'whites' => 100,
            'highlight_strength' => 80,
        ],
        'warm' => [
            'temperature' => 220,
            'tint' => 20,
            'vibrance' => 60,
            'highlights' => -60,
            'shadow_strength' => 40,
            'highlight_strength' => 100,
        ],
        'cool' => [
            'temperature' => -220,
            'tint' => 10,
            'saturation' => -30,
            'highlights' => -80,
            'shadow_strength' => 100,
            'highlight_strength' => 30,
        ],
        'moody' => [
            'exposure' => -20,
            'contrast' => 220,
            'saturation' => -100,
            'highlights' => -180,
            'shadows' => -120,
            'blacks' => -120,
            'fade' => 50,
            'shadow_strength' => 120,
            'highlight_strength' => 50,
        ],
        'vintage' => [
            'temperature' => 100,
            'contrast' => -40,
            'saturation' => -160,
            'highlights' => -80,
            'blacks' => 40,
            'fade' => 180,
            'shadow_strength' => 70,
            'highlight_strength' => 80,
        ],
        'pastel' => [
            'exposure' => 20,
            'contrast' => -130,
            'saturation' => -80,
            'vibrance' => 20,
            'highlights' => -150,
            'shadows' => 140,
            'whites' => 100,
            'fade' => 60,
            'highlight_strength' => 90,
        ],
    ];

    /**
     * @return list<array{
     *     attributes: array{
     *         type: ProductType,
     *         status: ProductStatus,
     *         name: string,
     *         slug: string,
     *         sku: string,
     *         short_description: string,
     *         description: string,
     *         price_cents: int,
     *         currency: string,
     *         is_featured: bool,
     *         is_testable: bool,
     *         published_at: string,
     *         meta_title: string,
     *         meta_description: string
     *     },
     *     primary_category_slug: string,
     *     category_slugs: list<string>,
     *     tag_slugs: list<string>,
     *     profile_number: int,
     *     source_asset: string,
     *     parameters: LutTransformParameters
     * }>
     */
    public function entries(): array
    {
        $entries = [];
        $categoryPosition = 0;

        foreach (self::PRIMARY_CATEGORIES as $categoryIndex => $category) {
            foreach (self::PROFILES as $profileIndex => $profile) {
                $profileNumber = $profileIndex + 1;
                $name = "{$profile['name']} {$category['name']} LUT";
                $shortDescription = "{$profile['character']} for {$category['subject']}.";
                $description = "The {$profile['name']} look combines {$profile['character']}. It is designed for {$category['subject']} while keeping the image readable and consistent.";
                $priceCents = 1700 + (($profileIndex * 3 + $categoryPosition * 7) % 13) * 100;
                $metaDescription = "A {$category['name']} LUT with {$profile['character']}.";

                if ($categoryIndex === 'travel' && isset(self::TRAVEL_OVERRIDES[$profileNumber])) {
                    $override = self::TRAVEL_OVERRIDES[$profileNumber];
                    $shortDescription = $override['short_description'];
                    $description = $override['description'];
                    $priceCents = $override['price_cents'];
                    $metaDescription = $override['meta_description'];
                }

                $entries[] = [
                    'attributes' => [
                        'type' => ProductType::SingleLut,
                        'status' => ProductStatus::Published,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'sku' => sprintf('PREVIEW-%s-%03d', $category['code'], $profileNumber),
                        'short_description' => $shortDescription,
                        'description' => $description,
                        'price_cents' => $priceCents,
                        'currency' => 'EUR',
                        'is_featured' => $categoryIndex === 'travel' && in_array($profileNumber, [1, 3], true),
                        'is_testable' => false,
                        'published_at' => sprintf('2026-06-%02d 09:00:00', $profileNumber),
                        'meta_title' => $name,
                        'meta_description' => $metaDescription,
                    ],
                    'primary_category_slug' => $categoryIndex,
                    'category_slugs' => $this->categorySlugs($categoryIndex, $profileNumber),
                    'tag_slugs' => $category['tags'],
                    'profile_number' => $profileNumber,
                    'source_asset' => "database/seeders/assets/storefront-preview/{$categoryIndex}.jpg",
                    'parameters' => $this->parameters($categoryIndex, $categoryPosition, $profileNumber),
                ];
            }

            $categoryPosition++;
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    public function primaryCategorySlugs(): array
    {
        return array_keys(self::PRIMARY_CATEGORIES);
    }

    /**
     * @return list<string>
     */
    private function categorySlugs(string $primaryCategorySlug, int $profileNumber): array
    {
        if ($primaryCategorySlug !== 'travel') {
            return [$primaryCategorySlug];
        }

        return match ($profileNumber) {
            1 => ['travel', 'cool', 'bright-clean'],
            3 => ['travel', 'cinematic', 'street', 'warm'],
            default => ['travel'],
        };
    }

    private function parameters(string $categorySlug, int $categoryPosition, int $profileNumber): LutTransformParameters
    {
        $profileDeltas = [
            'exposure' => ($profileNumber * 17) % 41 - 20,
            'contrast' => ($profileNumber * 73) % 361 - 180,
            'temperature' => ($profileNumber * 109) % 501 - 250,
            'tint' => ($profileNumber * 47) % 241 - 120,
            'saturation' => ($profileNumber * 61) % 361 - 180,
            'vibrance' => ($profileNumber * 83) % 321 - 80,
            'highlights' => -(($profileNumber * 67) % 201),
            'shadows' => ($profileNumber * 71) % 301 - 150,
            'whites' => ($profileNumber * 37) % 241 - 120,
            'blacks' => ($profileNumber * 53) % 281 - 140,
            'fade' => ($profileNumber * 43) % 221,
            'shadow_strength' => 80 + ($profileNumber * 29) % 171,
            'highlight_strength' => 70 + ($profileNumber * 31) % 181,
        ];
        $values = LutTransformParameters::defaults();

        foreach (array_keys($profileDeltas) as $key) {
            $values[$key] = $this->clampParameter(
                $key,
                $values[$key]
                    + (self::CATEGORY_PARAMETER_DELTAS[$categorySlug][$key] ?? 0)
                    + $profileDeltas[$key],
            );
        }

        foreach (self::CATEGORY_PARAMETER_DELTAS[$categorySlug] as $key => $delta) {
            if (! array_key_exists($key, $profileDeltas)) {
                $values[$key] = $this->clampParameter($key, $values[$key] + $delta);
            }
        }

        $globalProfileIndex = $categoryPosition * count(self::PROFILES) + $profileNumber - 1;
        $values['intensity'] = 900 + ($profileNumber * 7 + $categoryPosition * 3) % 101;
        $values['shadow_hue'] = $globalProfileIndex * 11;
        $values['highlight_hue'] = (400 + $categoryPosition * 230 + $profileNumber * 97) % 3600;

        return LutTransformParameters::fromArray($values);
    }

    private function clampParameter(string $key, int $value): int
    {
        return max(
            LutTransformParameters::minimum($key),
            min(LutTransformParameters::maximum($key), $value),
        );
    }
}
