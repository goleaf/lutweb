<?php

namespace App\Support\Storefront;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Str;

final class StorefrontPreviewCatalog
{
    /**
     * @var array<string, array{
     *     name: string,
     *     code: string,
     *     subject: string,
     *     primary_use: string,
     *     lighting: string,
     *     use_tags: list<string>,
     *     finish_tag: string
     * }>
     */
    private const PRIMARY_CATEGORIES = [
        'cinematic' => [
            'name' => 'Cinematic',
            'code' => 'CINEMATIC',
            'subject' => 'narrative scenes, short films, and dramatic sequences',
            'primary_use' => 'narrative photo and video',
            'lighting' => 'mixed practical light, blue hour, or controlled daylight',
            'use_tags' => ['for-night', 'for-daylight'],
            'finish_tag' => 'cinematic',
        ],
        'portrait' => [
            'name' => 'Portrait',
            'code' => 'PORTRAIT',
            'subject' => 'portraits, editorial sessions, and natural skin tones',
            'primary_use' => 'portrait and editorial work',
            'lighting' => 'window light, open shade, or a softly controlled studio setup',
            'use_tags' => ['for-portraits', 'for-daylight'],
            'finish_tag' => 'editorial',
        ],
        'travel' => [
            'name' => 'Travel',
            'code' => 'TRAVEL',
            'subject' => 'destinations, landscapes, and travel stories',
            'primary_use' => 'travel and landscape stories',
            'lighting' => 'open daylight, overcast weather, or late-afternoon sun',
            'use_tags' => ['for-travel', 'for-landscapes'],
            'finish_tag' => 'documentary',
        ],
        'street' => [
            'name' => 'Street',
            'code' => 'STREET',
            'subject' => 'city walks, architecture, and candid street frames',
            'primary_use' => 'street and architecture frames',
            'lighting' => 'overcast streets, hard daylight, mixed storefront light, or rain',
            'use_tags' => ['for-architecture', 'for-instagram'],
            'finish_tag' => 'documentary',
        ],
        'wedding' => [
            'name' => 'Wedding',
            'code' => 'WEDDING',
            'subject' => 'wedding films, ceremonies, and romantic portraits',
            'primary_use' => 'wedding and couple imagery',
            'lighting' => 'window light, open shade, backlight, or warm reception practicals',
            'use_tags' => ['for-weddings', 'for-portraits'],
            'finish_tag' => 'dreamy',
        ],
        'warm' => [
            'name' => 'Warm',
            'code' => 'WARM',
            'subject' => 'golden light, interiors, and sunlit lifestyle stories',
            'primary_use' => 'golden-hour and interior scenes',
            'lighting' => 'late-afternoon sun, warm window light, or practical-lit interiors',
            'use_tags' => ['for-golden-hour', 'for-interiors'],
            'finish_tag' => 'natural',
        ],
        'cool' => [
            'name' => 'Cool',
            'code' => 'COOL',
            'subject' => 'clean daylight, modern spaces, and winter scenery',
            'primary_use' => 'modern and cool-daylight scenes',
            'lighting' => 'overcast daylight, blue hour, winter sun, or clean interior light',
            'use_tags' => ['for-architecture', 'for-daylight'],
            'finish_tag' => 'modern',
        ],
        'moody' => [
            'name' => 'Moody',
            'code' => 'MOODY',
            'subject' => 'low-key portraits, atmospheric scenes, and deep shadows',
            'primary_use' => 'low-key and atmospheric work',
            'lighting' => 'low-key window light, fog, storm light, or restrained practicals',
            'use_tags' => ['for-night', 'for-portraits'],
            'finish_tag' => 'dramatic',
        ],
        'vintage' => [
            'name' => 'Vintage',
            'code' => 'VINTAGE',
            'subject' => 'nostalgic stories, analog-inspired frames, and timeless details',
            'primary_use' => 'nostalgic and analog-inspired scenes',
            'lighting' => 'soft daylight, tungsten interiors, or hazy late-afternoon light',
            'use_tags' => ['for-interiors', 'for-travel'],
            'finish_tag' => 'vintage',
        ],
        'pastel' => [
            'name' => 'Pastel',
            'code' => 'PASTEL',
            'subject' => 'soft portraits, airy details, and gentle lifestyle imagery',
            'primary_use' => 'airy portrait and lifestyle scenes',
            'lighting' => 'diffused daylight, open shade, or softly backlit conditions',
            'use_tags' => ['for-portraits', 'for-weddings'],
            'finish_tag' => 'dreamy',
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

    /** @var array<int, int> */
    private const TRAVEL_PRICE_OVERRIDES = [
        1 => 1900,
        2 => 2400,
        3 => 2100,
        4 => 1800,
        5 => 2200,
        6 => 2000,
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
                $parameters = $this->parameters($categoryIndex, $categoryPosition, $profileNumber);
                $descriptions = $this->descriptions(
                    $categoryIndex,
                    $category,
                    $profile,
                    $parameters,
                    $profileNumber,
                );
                $priceCents = 1700 + (($profileIndex * 3 + $categoryPosition * 7) % 13) * 100;

                if ($categoryIndex === 'travel' && isset(self::TRAVEL_PRICE_OVERRIDES[$profileNumber])) {
                    $priceCents = self::TRAVEL_PRICE_OVERRIDES[$profileNumber];
                }

                $entries[] = [
                    'attributes' => [
                        'type' => ProductType::SingleLut,
                        'status' => ProductStatus::Published,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'sku' => sprintf('PREVIEW-%s-%03d', $category['code'], $profileNumber),
                        'short_description' => $descriptions['short_description'],
                        'description' => $descriptions['description'],
                        'price_cents' => $priceCents,
                        'currency' => 'EUR',
                        'is_featured' => $categoryIndex === 'travel' && in_array($profileNumber, [1, 3], true),
                        'is_testable' => true,
                        'published_at' => sprintf('2026-06-%02d 09:00:00', $profileNumber),
                        'meta_title' => $name,
                        'meta_description' => $descriptions['meta_description'],
                    ],
                    'primary_category_slug' => $categoryIndex,
                    'category_slugs' => $this->categorySlugs($categoryIndex, $profileNumber),
                    'tag_slugs' => $this->tagSlugs($categoryIndex, $profile, $parameters),
                    'profile_number' => $profileNumber,
                    'source_asset' => sprintf(
                        'database/seeders/assets/storefront-preview/%s/%03d.jpg',
                        $categoryIndex,
                        $profileNumber,
                    ),
                    'parameters' => $parameters,
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

    /**
     * @param  array{name: string, character: string}  $profile
     * @return list<string>
     */
    private function tagSlugs(
        string $categorySlug,
        array $profile,
        LutTransformParameters $parameters,
    ): array {
        $category = self::PRIMARY_CATEGORIES[$categorySlug];
        $values = $parameters->toArray();
        $tags = array_values(array_unique([
            ...$category['use_tags'],
            ...$this->profileCharacterTags($profile),
            ...$this->parameterTags($values),
            $category['finish_tag'],
        ]));

        foreach ($this->supplementalParameterTags($values) as $tag) {
            if (count($tags) >= 7) {
                break;
            }

            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        $tags[] = $this->strengthTag($parameters);

        return array_slice(array_values(array_unique($tags)), 0, 12);
    }

    /**
     * @param  array{name: string, character: string}  $profile
     * @return list<string>
     */
    private function profileCharacterTags(array $profile): array
    {
        $character = Str::lower($profile['name'].' '.$profile['character']);

        return match (true) {
            Str::contains($character, ['skin', 'ivory', 'coral', 'peach', 'rose']) => ['skin-friendly', 'soft-highlights'],
            Str::contains($character, ['arctic', 'blue', 'chrome', 'cool', 'cyan', 'harbor', 'indigo', 'moonlit', 'nordic', 'silver']) => ['cool', 'teal'],
            Str::contains($character, ['amber', 'bronze', 'cedar', 'gold', 'honey', 'olive', 'sepia', 'warm']) => ['warm', 'amber'],
            Str::contains($character, ['faded', 'film', 'haze', 'linen', 'matte', 'muted', 'soft']) => ['muted-color', 'lifted-blacks'],
            Str::contains($character, ['contrast', 'crimson', 'dense', 'night', 'shadow', 'velvet']) => ['rich-color', 'deep-blacks'],
            default => ['natural-color', 'clean-whites'],
        };
    }

    /**
     * @param  array<string, int>  $values
     * @return list<string>
     */
    private function parameterTags(array $values): array
    {
        $tags = [
            match (true) {
                $values['temperature'] >= 150 => 'warm',
                $values['temperature'] <= -150 => 'cool',
                default => 'natural-color',
            },
            match (true) {
                $values['saturation'] <= -150 => 'muted-color',
                $values['vibrance'] >= 150 => 'rich-color',
                default => 'natural-color',
            },
            match (true) {
                $values['contrast'] >= 150 => 'high-contrast',
                $values['contrast'] <= -100 => 'low-contrast',
                default => 'open-shadows',
            },
            $values['highlights'] <= -150 ? 'protected-highlights' : 'soft-highlights',
            match (true) {
                $values['blacks'] <= -100 => 'deep-blacks',
                $values['blacks'] >= 100 => 'lifted-blacks',
                default => 'open-shadows',
            },
        ];

        if ($values['shadows'] >= 100) {
            $tags[] = 'open-shadows';
        }

        if ($values['whites'] >= 100) {
            $tags[] = 'clean-whites';
        }

        if ($values['fade'] >= 150) {
            $tags[] = 'matte';
        }

        return array_slice(array_values(array_unique($tags)), 0, 5);
    }

    /**
     * @param  array<string, int>  $values
     * @return list<string>
     */
    private function supplementalParameterTags(array $values): array
    {
        return array_values(array_unique([
            $values['highlights'] < 0 ? 'protected-highlights' : 'soft-highlights',
            $values['shadows'] >= 0 ? 'open-shadows' : 'deep-blacks',
            $values['whites'] >= 0 ? 'clean-whites' : 'natural-color',
            $values['fade'] >= 100 ? 'matte' : 'modern',
            'natural-color',
        ]));
    }

    private function strengthTag(LutTransformParameters $parameters): string
    {
        $defaults = LutTransformParameters::defaults();
        $values = $parameters->toArray();
        $distance = collect(LutTransformParameters::keys())
            ->reject(fn (string $key): bool => in_array($key, ['shadow_hue', 'highlight_hue'], true))
            ->sum(fn (string $key): int => abs($values[$key] - $defaults[$key]));

        return match (true) {
            $distance >= 2600 => 'bold-grade',
            $distance >= 1600 => 'balanced-grade',
            default => 'subtle-grade',
        };
    }

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     subject: string,
     *     primary_use: string,
     *     lighting: string,
     *     use_tags: list<string>,
     *     finish_tag: string
     * }  $category
     * @param  array{name: string, character: string}  $profile
     * @return array{short_description: string, description: string, meta_description: string}
     */
    private function descriptions(
        string $categorySlug,
        array $category,
        array $profile,
        LutTransformParameters $parameters,
        int $profileNumber,
    ): array {
        $values = $parameters->toArray();
        $palette = $this->paletteDescription($values);
        $tone = $this->toneDescription($values);
        $colorResponse = $this->colorResponseDescription($values);
        $strength = Str::before($this->strengthTag($parameters), '-grade');
        $shortDescription = "{$profile['name']} adds {$this->paletteLabel($values)} and {$this->toneLabel($values)} to {$category['name']} work, with {$colorResponse} for {$category['primary_use']}.";
        $description = "The {$profile['name']} profile shapes {$category['name']} imagery with {$palette}. Its {$tone} works with {$colorResponse}, while the split-toned shadows and highlights create visible separation without hiding important texture. At full intensity, the transform gives profile {$profileNumber} a distinct response across the 30-look {$categorySlug} collection.\n\nUse it for {$category['subject']}, especially in {$category['lighting']}. The {$strength} grade is designed to establish a clear starting look while leaving exposure and white-balance decisions tied to the source image and the needs of the edit.";
        $metaDescription = "{$profile['name']} {$category['name']} LUT brings {$this->paletteLabel($values)}, {$this->toneLabel($values)}, and {$strength} color to {$category['primary_use']}, preserving tonal detail.";

        return [
            'short_description' => $shortDescription,
            'description' => $description,
            'meta_description' => $metaDescription,
        ];
    }

    /** @param array<string, int> $values */
    private function paletteDescription(array $values): string
    {
        $base = $this->paletteLabel($values);
        $shadowAccent = match (true) {
            $values['shadow_hue'] >= 1500 && $values['shadow_hue'] <= 2300 => 'teal-blue shadows',
            $values['shadow_hue'] >= 2301 && $values['shadow_hue'] <= 2900 => 'indigo shadows',
            $values['shadow_hue'] <= 600 || $values['shadow_hue'] >= 3300 => 'red-amber shadows',
            default => 'restrained colored shadows',
        };

        return "{$base} and {$shadowAccent}";
    }

    /** @param array<string, int> $values */
    private function paletteLabel(array $values): string
    {
        return match (true) {
            $values['temperature'] >= 150 => 'warm amber color',
            $values['temperature'] <= -150 => 'cool cyan color',
            default => 'balanced neutral color',
        };
    }

    /** @param array<string, int> $values */
    private function toneDescription(array $values): string
    {
        $contrast = $this->toneLabel($values);
        $highlights = $values['highlights'] <= -150 ? 'protected highlights' : 'softened highlights';
        $shadows = $values['shadows'] >= 100 ? 'open shadows' : 'shaped shadows';
        $blacks = $values['blacks'] <= -100 ? 'deep blacks' : 'readable blacks';

        return "{$contrast}, {$highlights}, {$shadows}, and {$blacks}";
    }

    /** @param array<string, int> $values */
    private function toneLabel(array $values): string
    {
        return match (true) {
            $values['contrast'] >= 150 => 'firm contrast',
            $values['contrast'] <= -100 => 'soft contrast',
            default => 'balanced contrast',
        };
    }

    /** @param array<string, int> $values */
    private function colorResponseDescription(array $values): string
    {
        return match (true) {
            $values['saturation'] <= -150 => 'controlled, muted color',
            $values['vibrance'] >= 150 => 'rich color density',
            default => 'natural color separation',
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
                    + $this->strengthenedDelta(self::CATEGORY_PARAMETER_DELTAS[$categorySlug][$key] ?? 0)
                    + $this->strengthenedDelta($profileDeltas[$key]),
            );
        }

        foreach (self::CATEGORY_PARAMETER_DELTAS[$categorySlug] as $key => $delta) {
            if (! array_key_exists($key, $profileDeltas)) {
                $values[$key] = $this->clampParameter(
                    $key,
                    $values[$key] + $this->strengthenedDelta($delta),
                );
            }
        }

        $globalProfileIndex = $categoryPosition * count(self::PROFILES) + $profileNumber - 1;
        $values['intensity'] = 1000;
        $values['shadow_hue'] = $globalProfileIndex * 11;
        $values['highlight_hue'] = (400 + $categoryPosition * 230 + $profileNumber * 97) % 3600;
        $values['shadow_strength'] = max(125, $values['shadow_strength']);
        $values['highlight_strength'] = max(125, $values['highlight_strength']);
        $values = $this->enforceControlSeparation($values);

        return LutTransformParameters::fromArray($values);
    }

    private function strengthenedDelta(int $delta): int
    {
        return intdiv($delta * 5, 4);
    }

    /**
     * @param  array<string, int>  $values
     * @return array<string, int>
     */
    private function enforceControlSeparation(array $values): array
    {
        $defaults = LutTransformParameters::defaults();
        $nonHueKeys = array_values(array_diff(
            LutTransformParameters::keys(),
            ['shadow_hue', 'highlight_hue'],
        ));
        $largeControlCount = collect($nonHueKeys)
            ->filter(fn (string $key): bool => abs($values[$key] - $defaults[$key]) >= 150)
            ->count();

        if ($largeControlCount >= 4) {
            return $values;
        }

        $paletteAndToneKeys = [
            'contrast',
            'temperature',
            'tint',
            'saturation',
            'vibrance',
            'highlights',
            'shadows',
            'whites',
            'blacks',
            'fade',
        ];

        usort(
            $paletteAndToneKeys,
            fn (string $left, string $right): int => abs($values[$right] - $defaults[$right])
                <=> abs($values[$left] - $defaults[$left]),
        );

        foreach ($paletteAndToneKeys as $key) {
            if ($largeControlCount >= 4) {
                break;
            }

            $distance = $values[$key] - $defaults[$key];

            if (abs($distance) >= 150) {
                continue;
            }

            $direction = $distance < 0 ? -1 : 1;
            $values[$key] = $this->clampParameter($key, $defaults[$key] + $direction * 150);
            $largeControlCount++;
        }

        return $values;
    }

    private function clampParameter(string $key, int $value): int
    {
        return max(
            LutTransformParameters::minimum($key),
            min(LutTransformParameters::maximum($key), $value),
        );
    }
}
