<?php

use App\Support\Storefront\StorefrontPreviewCatalog;
use App\ValueObjects\LutTransformParameters;

test('preview catalog defines unique color parameters and one source scene per category', function (): void {
    $entries = (new StorefrontPreviewCatalog)->entries();
    $parameters = array_values(array_filter(
        array_map(fn (array $entry): mixed => $entry['parameters'] ?? null, $entries),
        fn (mixed $parameters): bool => $parameters instanceof LutTransformParameters,
    ));
    $sourceAssets = array_values(array_unique(array_filter(
        array_map(fn (array $entry): mixed => $entry['source_asset'] ?? null, $entries),
        is_string(...),
    )));
    $existingSourceAssets = array_values(array_filter(
        $sourceAssets,
        fn (string $path): bool => is_file(dirname(__DIR__, 2).'/'.$path),
    ));

    expect($entries)->toHaveCount(300)
        ->and($parameters)->toHaveCount(300)
        ->and(array_unique(array_map(fn (LutTransformParameters $item): string => $item->hash(), $parameters)))
        ->toHaveCount(300)
        ->and($sourceAssets)->toHaveCount(10)
        ->and($existingSourceAssets)->toHaveCount(10);
});
