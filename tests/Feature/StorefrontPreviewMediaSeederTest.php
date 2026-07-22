<?php

use App\Actions\Storefront\GenerateStorefrontPreviewCover;
use App\Actions\Storefront\GenerateStorefrontPreviewExample;
use App\Actions\Storefront\GenerateStorefrontPreviewPackage;
use App\Enums\ProductFileKind;
use App\Enums\ProductVersionStatus;
use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;
use App\Services\LutTester\ProductLutTestEligibility;
use App\Support\Storefront\StorefrontPreviewCatalog;
use Database\Seeders\StorefrontPreviewMediaSeeder;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

test('preview example generation creates idempotent before and after variants', function (): void {
    if (! class_exists(GenerateStorefrontPreviewExample::class)) {
        expect(class_exists(GenerateStorefrontPreviewExample::class))->toBeTrue();

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

    $example = app(GenerateStorefrontPreviewExample::class)->handle($product, $entry);
    $example->load('variants');
    $initialVariantIds = $example->variants->pluck('id')->sort()->values()->all();
    $initialBeforePaths = $example->beforeVariants()->pluck('path')->sort()->values()->all();
    $initialAfterPaths = $example->afterVariants()->pluck('path')->sort()->values()->all();
    $initialFingerprint = $example->processing_fingerprint;

    expect($example->title)->toBe('Original vs '.$product->name)
        ->and($example->before_path)->toBe('')
        ->and($example->after_path)->toBe('')
        ->and($example->processing_status)->toBe(StorefrontImageStatus::Ready)
        ->and($example->processing_fingerprint)->toHaveLength(64)
        ->and($example->rights_confirmed_at)->not->toBeNull()
        ->and($example->processed_product_version_id)->toBeNull()
        ->and($example->processed_product_file_id)->toBeNull()
        ->and($example->source_width)->toBe(1600)
        ->and($example->source_height)->toBe(1200)
        ->and($example->variants)->toHaveCount(16)
        ->and($example->beforeVariants())->toHaveCount(8)
        ->and($example->afterVariants())->toHaveCount(8)
        ->and($example->variants->pluck('width')->unique()->sort()->values()->all())
        ->toBe([480, 768, 1200, 1600])
        ->and($example->variants->pluck('role')->map->value->unique()->sort()->values()->all())
        ->toBe([StorefrontImageVariantRole::After->value, StorefrontImageVariantRole::Before->value]);

    foreach ($example->variants as $variant) {
        Storage::disk('public')->assertExists($variant->path);
    }

    $example->forceFill([
        'processing_status' => StorefrontImageStatus::Stale,
        'stale_at' => now(),
    ])->save();

    $reprocessed = app(GenerateStorefrontPreviewExample::class)->handle($product, $entry);
    $reprocessed->load('variants');

    expect($reprocessed->id)->toBe($example->id)
        ->and($reprocessed->processing_status)->toBe(StorefrontImageStatus::Ready)
        ->and($reprocessed->stale_at)->toBeNull()
        ->and($reprocessed->variants->pluck('id')->sort()->values()->all())->toBe($initialVariantIds);

    $changedEntry = $entry;
    $changedEntry['parameters'] = collect((new StorefrontPreviewCatalog)->entries())
        ->first(fn (array $candidate): bool => $candidate['attributes']['sku'] === 'PREVIEW-TRAVEL-002')['parameters'];
    $regenerated = app(GenerateStorefrontPreviewExample::class)->handle($product, $changedEntry);
    $regenerated->load('variants');
    $regeneratedPaths = $regenerated->variants->pluck('path')->sort()->values()->all();
    $regeneratedBeforePaths = $regenerated->beforeVariants()->pluck('path')->sort()->values()->all();
    $regeneratedAfterPaths = $regenerated->afterVariants()->pluck('path')->sort()->values()->all();

    expect($regenerated->id)->toBe($example->id)
        ->and($regenerated->processing_status)->toBe(StorefrontImageStatus::Ready)
        ->and($regenerated->processing_fingerprint)->not->toBe($initialFingerprint)
        ->and($regenerated->variants)->toHaveCount(16)
        ->and($regeneratedBeforePaths)->toBe($initialBeforePaths)
        ->and($regeneratedAfterPaths)->not->toBe($initialAfterPaths);

    foreach ($initialAfterPaths as $path) {
        Storage::disk('public')->assertMissing($path);
    }

    foreach ($regeneratedPaths as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('preview package generation creates private sale-ready product files idempotently', function (): void {
    if (! class_exists(GenerateStorefrontPreviewPackage::class)) {
        expect(class_exists(GenerateStorefrontPreviewPackage::class))->toBeTrue();

        return;
    }

    $ffmpeg = (string) config('custom-lut-builds.ffmpeg_binary', 'ffmpeg');

    if (Process::timeout(5)->run([$ffmpeg, '-version'])->failed()) {
        $this->markTestSkipped('The configured FFmpeg binary is not available.');
    }

    $this->artisan('db:seed', [
        '--class' => 'Database\\Seeders\\StorefrontPreviewSeeder',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    Storage::fake('private');
    config([
        'custom-lut-builds.private_disk' => 'private',
        'custom-lut-builds.cube_sizes' => [17, 33, 65],
        'custom-lut-builds.ffmpeg_validation_enabled' => true,
    ]);

    $product = Product::query()->where('sku', 'PREVIEW-TRAVEL-001')->firstOrFail();
    $entry = collect((new StorefrontPreviewCatalog)->entries())
        ->first(fn (array $entry): bool => $entry['attributes']['sku'] === $product->sku);

    expect($entry)->toBeArray();

    $deprecations = [];
    set_error_handler(function (int $severity, string $message) use (&$deprecations): bool {
        if ($severity !== E_DEPRECATED) {
            return false;
        }

        $deprecations[] = $message;

        return true;
    });

    try {
        $version = app(GenerateStorefrontPreviewPackage::class)->handle($product, $entry);
    } finally {
        restore_error_handler();
    }

    $version->load('files');
    $initialFileIds = $version->files->pluck('id')->sort()->values()->all();
    $initialPaths = $version->files->pluck('path')->sort()->values()->all();

    expect($deprecations)->toBe([])
        ->and($version->status)->toBe(ProductVersionStatus::Ready)
        ->and($version->is_current)->toBeTrue()
        ->and($product->refresh()->is_testable)->toBeTrue()
        ->and(app(ProductLutTestEligibility::class)->canTest($product))->toBeTrue()
        ->and($version->version)->toStartWith('preview-')
        ->and($version->files)->toHaveCount(5)
        ->and($version->files->pluck('kind')->map->value->sort()->values()->all())->toBe([
            ProductFileKind::Cube17->value,
            ProductFileKind::Cube33->value,
            ProductFileKind::Cube65->value,
            ProductFileKind::PackageZip->value,
            ProductFileKind::Readme->value,
        ]);

    foreach ($version->files as $file) {
        expect($file->disk)->toBe('private')
            ->and($file->path)->toStartWith('products/storefront-preview/preview-travel-001/')
            ->and($file->size_bytes)->toBeGreaterThan(0)
            ->and($file->sha256)->toMatch('/^[a-f0-9]{64}$/');

        Storage::disk('private')->assertExists($file->path);

        $stream = Storage::disk('private')->readStream($file->path);

        expect($stream)->toBeResource();

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        expect(hash_final($context))->toBe($file->sha256);
    }

    foreach ([
        ProductFileKind::Cube17->value => 17,
        ProductFileKind::Cube33->value => 33,
        ProductFileKind::Cube65->value => 65,
    ] as $kind => $size) {
        $file = $version->files->first(fn (ProductFile $file): bool => $file->kind->value === $kind);

        expect($file)->toBeInstanceOf(ProductFile::class)
            ->and(Storage::disk('private')->get($file->path))->toContain('LUT_3D_SIZE '.$size);
    }

    $readme = $version->files->first(fn (ProductFile $file): bool => $file->kind === ProductFileKind::Readme);

    expect($readme)->toBeInstanceOf(ProductFile::class)
        ->and(Storage::disk('private')->get($readme->path))->toContain(
            'LICENSED CUSTOMER PACKAGE',
            'Use the 33-point CUBE first',
            'goleaf@gmail.com',
        )->not->toContain('technical preview', 'Checkout must remain disabled', 'preview-only');

    $package = $version->files->first(fn (ProductFile $file): bool => $file->kind === ProductFileKind::PackageZip);

    expect($package)->toBeInstanceOf(ProductFile::class);

    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('private')->path($package->path)))->toBeTrue();

    $zipEntries = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);

        expect($name)->toBeString()
            ->and($name)->not->toContain('..', '\\')
            ->and($name)->not->toStartWith('/');

        $zipEntries[] = $name;
    }

    $zip->close();
    sort($zipEntries);

    expect($zipEntries)->toBe([
        'CHECKSUMS.txt',
        'CUBE/alpine-morning-travel-lut-17.cube',
        'CUBE/alpine-morning-travel-lut-33.cube',
        'CUBE/alpine-morning-travel-lut-65.cube',
        'README.txt',
        'manifest.json',
    ]);

    $reprocessed = app(GenerateStorefrontPreviewPackage::class)->handle($product, $entry);
    $reprocessed->load('files');

    expect($reprocessed)->toBeInstanceOf(ProductVersion::class)
        ->and($reprocessed->id)->toBe($version->id)
        ->and($reprocessed->files->pluck('id')->sort()->values()->all())->toBe($initialFileIds)
        ->and($reprocessed->files->pluck('path')->sort()->values()->all())->toBe($initialPaths)
        ->and(ProductVersion::query()->whereBelongsTo($product)->count())->toBe(1);
});

test('preview media seeder generates cover package and example in order for every catalog product', function (): void {
    $entryCount = count((new StorefrontPreviewCatalog)->entries());
    $calls = [];

    expect($entryCount)->toBe(300);

    $coverGenerator = Mockery::mock(GenerateStorefrontPreviewCover::class);
    $coverGenerator->shouldReceive('handle')
        ->times($entryCount)
        ->withArgs(fn (Product $product, array $entry): bool => $entry['attributes']['sku'] === $product->sku)
        ->andReturnUsing(function (Product $product) use (&$calls): ProductMedia {
            $calls[] = 'cover:'.$product->sku;

            return new ProductMedia;
        });
    $packageGenerator = Mockery::mock(GenerateStorefrontPreviewPackage::class);
    $packageGenerator->shouldReceive('handle')
        ->times($entryCount)
        ->withArgs(fn (Product $product, array $entry): bool => $entry['attributes']['sku'] === $product->sku)
        ->andReturnUsing(function (Product $product) use (&$calls): ProductVersion {
            $calls[] = 'package:'.$product->sku;

            return new ProductVersion;
        });
    $exampleGenerator = Mockery::mock(GenerateStorefrontPreviewExample::class);
    $exampleGenerator->shouldReceive('handle')
        ->times($entryCount)
        ->withArgs(fn (Product $product, array $entry): bool => $entry['attributes']['sku'] === $product->sku)
        ->andReturnUsing(function (Product $product) use (&$calls): ProductExample {
            $calls[] = 'example:'.$product->sku;

            return new ProductExample;
        });

    $this->app->instance(GenerateStorefrontPreviewCover::class, $coverGenerator);
    $this->app->instance(GenerateStorefrontPreviewPackage::class, $packageGenerator);
    $this->app->instance(GenerateStorefrontPreviewExample::class, $exampleGenerator);

    $this->artisan('db:seed', [
        '--class' => StorefrontPreviewMediaSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($calls)->toHaveCount($entryCount * 3);

    foreach (array_chunk($calls, 3) as $productCalls) {
        $sku = Str::after($productCalls[0], 'cover:');

        expect($productCalls)->toBe([
            'cover:'.$sku,
            'package:'.$sku,
            'example:'.$sku,
        ]);
    }
});
