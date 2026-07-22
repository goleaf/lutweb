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
