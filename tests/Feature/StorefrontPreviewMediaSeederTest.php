<?php

use App\Actions\Storefront\GenerateStorefrontPreviewCover;
use App\Enums\StorefrontImageStatus;
use App\Models\Product;
use App\Support\Storefront\StorefrontPreviewCatalog;
use Database\Seeders\StorefrontPreviewMediaSeeder;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

test('preview cover generation uses ffmpeg and is idempotent', function (): void {
    if (! class_exists(GenerateStorefrontPreviewCover::class) || ! class_exists(StorefrontPreviewMediaSeeder::class)) {
        expect(class_exists(GenerateStorefrontPreviewCover::class))->toBeTrue()
            ->and(class_exists(StorefrontPreviewMediaSeeder::class))->toBeTrue();

        return;
    }

    $ffmpeg = (string) config('lut-tester.ffmpeg_binary', 'ffmpeg');

    if (Process::timeout(5)->run([$ffmpeg, '-version'])->failed()) {
        $this->markTestSkipped('The configured FFmpeg binary is not available.');
    }

    if (! function_exists('imagejpeg') || ! function_exists('imagewebp')) {
        $this->markTestSkipped('GD JPEG/WebP support is unavailable.');
    }

    $this->artisan('db:seed', [
        '--class' => 'Database\\Seeders\\StorefrontPreviewSeeder',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    Storage::fake('public');
    config([
        'storefront-media.public_disk' => 'public',
        'storefront-media.public_prefix' => 'storefront',
        'storefront-media.responsive_widths' => [480, 768, 1200, 1600],
    ]);

    $product = Product::query()->where('sku', 'PREVIEW-TRAVEL-001')->firstOrFail();
    $entry = collect((new StorefrontPreviewCatalog)->entries())
        ->first(fn (array $entry): bool => $entry['attributes']['sku'] === $product->sku);

    expect($entry)->toBeArray();

    $media = app(GenerateStorefrontPreviewCover::class)->handle($product, $entry);
    $media->load('variants');
    $initialVariantIds = $media->variants->pluck('id')->sort()->values()->all();

    expect($media->path)->toBe('')
        ->and($media->processing_status)->toBe(StorefrontImageStatus::Ready)
        ->and($media->processing_fingerprint)->toHaveLength(64)
        ->and($media->rights_confirmed_at)->not->toBeNull()
        ->and($media->source_width)->toBe(1600)
        ->and($media->source_height)->toBe(1200)
        ->and($media->variants)->toHaveCount(8)
        ->and($media->variants->pluck('width')->unique()->sort()->values()->all())
        ->toBe([480, 768, 1200, 1600]);

    foreach ($media->variants as $variant) {
        Storage::disk('public')->assertExists($variant->path);
    }

    $reprocessed = app(GenerateStorefrontPreviewCover::class)->handle($product, $entry);
    $reprocessed->load('variants');

    expect($reprocessed->id)->toBe($media->id)
        ->and($reprocessed->variants->pluck('id')->sort()->values()->all())->toBe($initialVariantIds);
});
