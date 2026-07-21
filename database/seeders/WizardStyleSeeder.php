<?php

namespace Database\Seeders;

use App\Enums\LutTransformVersion;
use App\Models\WizardStyle;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WizardStyleSeeder extends Seeder
{
    /**
     * Seed administrator-managed Custom LUT Wizard styles.
     */
    public function run(): void
    {
        foreach ($this->styles() as $sortOrder => $style) {
            $base = LutTransformParameters::neutral()->withChanges($style['base'])->toArray();

            WizardStyle::query()->updateOrCreate(
                ['slug' => Str::slug($style['name'])],
                [
                    'name' => $style['name'],
                    'description' => $style['description'],
                    'transform_version' => LutTransformVersion::V1,
                    'base_parameters' => $base,
                    'minimum_parameters' => $this->range($base, -1),
                    'maximum_parameters' => $this->range($base, 1),
                    'variation_amounts' => $style['variation_amounts'],
                    'is_active' => true,
                    'is_featured' => $sortOrder < 3,
                    'sort_order' => $sortOrder,
                ],
            );
        }
    }

    /**
     * @return list<array{name: string, description: string, base: array<string, int>, variation_amounts: array<string, int>}>
     */
    private function styles(): array
    {
        return [
            [
                'name' => 'Clean Portrait',
                'description' => 'Natural contrast and restrained warmth for people-first edits.',
                'base' => [
                    'temperature' => 60,
                    'tint' => 20,
                    'contrast' => 80,
                    'highlights' => -60,
                    'shadows' => 60,
                    'vibrance' => 50,
                ],
                'variation_amounts' => $this->variationAmounts(70),
            ],
            [
                'name' => 'Warm Cinematic',
                'description' => 'A gentle warm grade with richer contrast and soft highlights.',
                'base' => [
                    'temperature' => 180,
                    'contrast' => 180,
                    'highlights' => -120,
                    'shadows' => 80,
                    'saturation' => -40,
                    'highlight_hue' => 420,
                    'highlight_strength' => 80,
                ],
                'variation_amounts' => $this->variationAmounts(100),
            ],
            [
                'name' => 'Dark Moody',
                'description' => 'Deeper blacks, stronger contrast, and subdued color.',
                'base' => [
                    'contrast' => 260,
                    'exposure' => -20,
                    'saturation' => -160,
                    'shadows' => -100,
                    'blacks' => -160,
                    'highlights' => -80,
                ],
                'variation_amounts' => $this->variationAmounts(110),
            ],
            [
                'name' => 'Soft Pastel',
                'description' => 'Lower contrast, lifted blacks, and a gentle faded finish.',
                'base' => [
                    'contrast' => -160,
                    'saturation' => -100,
                    'vibrance' => 80,
                    'blacks' => 160,
                    'fade' => 180,
                    'highlights' => -80,
                ],
                'variation_amounts' => $this->variationAmounts(90),
            ],
            [
                'name' => 'Vintage Film',
                'description' => 'Reduced saturation, mild fade, and warm highlight color.',
                'base' => [
                    'temperature' => 120,
                    'saturation' => -180,
                    'contrast' => -60,
                    'fade' => 240,
                    'highlight_hue' => 480,
                    'highlight_strength' => 100,
                    'shadow_hue' => 2150,
                    'shadow_strength' => 60,
                ],
                'variation_amounts' => $this->variationAmounts(100),
            ],
            [
                'name' => 'Bright Travel',
                'description' => 'Open shadows, restrained highlights, and moderate vibrance.',
                'base' => [
                    'exposure' => 15,
                    'contrast' => 100,
                    'vibrance' => 180,
                    'saturation' => 60,
                    'shadows' => 140,
                    'highlights' => -100,
                    'whites' => 80,
                ],
                'variation_amounts' => $this->variationAmounts(90),
            ],
        ];
    }

    /**
     * @param  array<string, int>  $base
     * @return array<string, int>
     */
    private function range(array $base, int $direction): array
    {
        $range = [];

        foreach ($base as $key => $value) {
            if (LutTransformParameters::isHueKey($key)) {
                $range[$key] = $direction < 0
                    ? LutTransformParameters::minimum($key)
                    : LutTransformParameters::maximum($key);

                continue;
            }

            $span = match ($key) {
                'intensity' => 300,
                'exposure' => 80,
                'fade', 'shadow_strength', 'highlight_strength' => 280,
                default => 360,
            };

            $candidate = $value + ($direction * $span);
            $range[$key] = max(LutTransformParameters::minimum($key), min(LutTransformParameters::maximum($key), $candidate));
        }

        return $range;
    }

    /**
     * @return array<string, int>
     */
    private function variationAmounts(int $defaultAmount): array
    {
        $amounts = [];

        foreach (LutTransformParameters::keys() as $key) {
            $amounts[$key] = match ($key) {
                'intensity' => 0,
                'exposure' => 10,
                'fade', 'shadow_strength', 'highlight_strength' => min(80, $defaultAmount),
                'shadow_hue', 'highlight_hue' => 120,
                default => $defaultAmount,
            };
        }

        return $amounts;
    }
}
