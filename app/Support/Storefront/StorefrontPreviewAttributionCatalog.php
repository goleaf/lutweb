<?php

namespace App\Support\Storefront;

use JsonException;
use RuntimeException;

final class StorefrontPreviewAttributionCatalog
{
    /**
     * @var array<string, string>
     */
    private const CATEGORIES = [
        'cinematic' => 'Cinematic',
        'portrait' => 'Portrait',
        'travel' => 'Travel',
        'street' => 'Street',
        'wedding' => 'Wedding',
        'warm' => 'Warm',
        'cool' => 'Cool',
        'moody' => 'Moody',
        'vintage' => 'Vintage',
        'pastel' => 'Pastel',
    ];

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     sources: list<array{
     *         key: string,
     *         title: string,
     *         creator: string,
     *         license: string,
     *         license_url: string,
     *         source_page: string,
     *         modification: string,
     *         reuse_notice: string,
     *         attribution_required: bool,
     *         share_alike_required: bool
     *     }>
     * }>
     */
    public function categories(): array
    {
        $attributions = $this->readAttributions();
        $categories = [];

        foreach (self::CATEGORIES as $slug => $name) {
            $sources = [];

            foreach ($attributions as $key => $attribution) {
                if (($attribution['category'] ?? null) !== $slug) {
                    continue;
                }

                $sources[] = [
                    'key' => $key,
                    'title' => $this->requiredString($attribution, 'title', $key),
                    'creator' => $this->requiredString($attribution, 'creator', $key),
                    'license' => $this->requiredString($attribution, 'license', $key),
                    'license_url' => $this->requiredString($attribution, 'license_url', $key),
                    'source_page' => $this->requiredString($attribution, 'source_page', $key),
                    'modification' => $this->requiredString($attribution, 'modification', $key),
                    'reuse_notice' => $this->requiredString($attribution, 'reuse_notice', $key),
                    'attribution_required' => (bool) ($attribution['attribution_required'] ?? false),
                    'share_alike_required' => (bool) ($attribution['share_alike_required'] ?? false),
                ];
            }

            $categories[] = [
                'slug' => $slug,
                'name' => $name,
                'sources' => $sources,
            ];
        }

        return $categories;
    }

    /** @return array<string, array<string, mixed>> */
    private function readAttributions(): array
    {
        $path = database_path('seeders/assets/storefront-preview/attribution.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read storefront preview attribution catalog at {$path}.");
        }

        try {
            $attributions = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Storefront preview attribution catalog contains invalid JSON.', previous: $exception);
        }

        if (! is_array($attributions)) {
            throw new RuntimeException('Storefront preview attribution catalog must contain an object.');
        }

        return $attributions;
    }

    /** @param array<string, mixed> $attribution */
    private function requiredString(array $attribution, string $field, string $key): string
    {
        $value = $attribution[$field] ?? null;

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Storefront preview attribution {$key} is missing {$field}.");
        }

        return $value;
    }
}
