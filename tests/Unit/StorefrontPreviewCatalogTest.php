<?php

use App\Support\Storefront\StorefrontPreviewCatalog;
use App\ValueObjects\LutTransformParameters;

test('preview catalog defines sale-ready content for every LUT', function (): void {
    $entries = (new StorefrontPreviewCatalog)->entries();
    $parameters = array_values(array_filter(
        array_map(fn (array $entry): mixed => $entry['parameters'] ?? null, $entries),
        fn (mixed $parameters): bool => $parameters instanceof LutTransformParameters,
    ));
    $sourceAssets = array_values(array_filter(
        array_map(fn (array $entry): mixed => $entry['source_asset'] ?? null, $entries),
        is_string(...),
    ));
    $sourceHashes = array_map(function (string $path): string {
        $absolutePath = dirname(__DIR__, 2).'/'.$path;

        return is_file($absolutePath) ? (hash_file('sha256', $absolutePath) ?: '') : '';
    }, $sourceAssets);

    expect($entries)->toHaveCount(300)
        ->and($parameters)->toHaveCount(300)
        ->and(array_unique(array_map(fn (LutTransformParameters $item): string => $item->hash(), $parameters)))
        ->toHaveCount(300)
        ->and(array_unique($sourceAssets))->toHaveCount(300)
        ->and(array_filter($sourceHashes))->toHaveCount(300)
        ->and(array_unique($sourceHashes))->toHaveCount(300)
        ->and(array_unique(array_column(array_column($entries, 'attributes'), 'short_description')))->toHaveCount(300)
        ->and(array_unique(array_column(array_column($entries, 'attributes'), 'description')))->toHaveCount(300)
        ->and(array_unique(array_column(array_column($entries, 'attributes'), 'meta_description')))->toHaveCount(300);

    foreach ($entries as $entry) {
        $attributes = $entry['attributes'];
        $values = $entry['parameters']->toArray();
        $defaults = LutTransformParameters::defaults();
        $nonHueKeys = array_values(array_diff(LutTransformParameters::keys(), ['shadow_hue', 'highlight_hue']));
        $distances = collect($nonHueKeys)->map(fn (string $key): int => abs($values[$key] - $defaults[$key]));

        expect(is_file(dirname(__DIR__, 2).'/'.$entry['source_asset']))->toBeTrue()
            ->and($attributes['is_testable'])->toBeTrue()
            ->and(mb_strlen($attributes['short_description']))->toBeBetween(80, 180)
            ->and(mb_strlen($attributes['description']))->toBeGreaterThanOrEqual(280)
            ->and(mb_strlen($attributes['meta_description']))->toBeBetween(120, 160)
            ->and(count($entry['tag_slugs']))->toBeBetween(8, 12)
            ->and(array_unique($entry['tag_slugs']))->toHaveCount(count($entry['tag_slugs']))
            ->and($entry['parameters']->intensity())->toBe(1000)
            ->and($distances->filter(fn (int $distance): bool => $distance >= 150)->count())->toBeGreaterThanOrEqual(4)
            ->and($distances->sum())->toBeGreaterThanOrEqual(1200)
            ->and($values['shadow_strength'] + $values['highlight_strength'])->toBeGreaterThanOrEqual(250);
    }
});

test('every preview source has complete reusable Wikimedia Commons attribution', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $attributionPath = $projectRoot.'/database/seeders/assets/storefront-preview/attribution.json';
    $attributions = json_decode(
        file_get_contents($attributionPath) ?: '',
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $catalogKeys = collect((new StorefrontPreviewCatalog)->entries())
        ->map(fn (array $entry): string => preg_replace(
            '#^database/seeders/assets/storefront-preview/(.+)\.jpg$#',
            '$1',
            $entry['source_asset'],
        ))
        ->sort()
        ->values()
        ->all();

    expect($attributions)->toBeArray()
        ->and($attributions)->toHaveCount(300)
        ->and(array_keys($attributions))->toBe($catalogKeys)
        ->and(array_unique(array_column($attributions, 'source_sha1')))->toHaveCount(300)
        ->and(array_unique(array_column($attributions, 'local_sha256')))->toHaveCount(300);

    foreach ($attributions as $key => $attribution) {
        $assetPath = $projectRoot.'/database/seeders/assets/storefront-preview/'.$key.'.jpg';
        $dimensions = getimagesize($assetPath);

        expect($attribution['title'])->toStartWith('File:')
            ->and($attribution['creator'])->not->toBeEmpty()
            ->and($attribution['license'])->toMatch('/^(?:CC0|Public domain|CC BY(?:-SA)? (?:1\.0|2\.0|2\.5|3\.0|4\.0))$/')
            ->and($attribution['license_url'])->toStartWith('https://')
            ->and($attribution['source_page'])->toStartWith('https://commons.wikimedia.org/wiki/File')
            ->and($attribution['original_url'])->toStartWith('https://upload.wikimedia.org/')
            ->and($attribution['download_url'])->toStartWith('https://upload.wikimedia.org/')
            ->and($attribution['source_sha1'])->toMatch('/^[a-f0-9]{40}$/')
            ->and($attribution['local_sha256'])->toBe(hash_file('sha256', $assetPath))
            ->and($attribution['modification'])->toBe('Cropped and resized to 1600×1200; color unchanged.')
            ->and($attribution['restrictions'])->toBe([])
            ->and($attribution['reuse_notice'])->not->toBeEmpty()
            ->and($attribution['share_alike_required'])->toBe(
                str_starts_with($attribution['license'], 'CC BY-SA'),
            )
            ->and($dimensions)->toBeArray()
            ->and($dimensions[0])->toBe(1600)
            ->and($dimensions[1])->toBe(1200)
            ->and($dimensions['mime'])->toBe('image/jpeg');
    }
});
