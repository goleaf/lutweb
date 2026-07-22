<?php

namespace App\Actions\Storefront;

use App\Color\CubeSize;
use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\StorefrontImageVariant;
use App\Services\CustomLutBuilds\PackageName;
use App\Services\CustomLutBuilds\WriteCubeFile;
use App\Services\LutTester\ApplyPreviewWatermark;
use App\Services\StorefrontMedia\DeleteStorefrontImageVariants;
use App\Services\StorefrontMedia\GenerateStorefrontImageVariants;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateStorefrontPreviewExample
{
    public function __construct(
        private readonly WriteCubeFile $writeCubeFile,
        private readonly GenerateStorefrontImageVariants $generateVariants,
        private readonly DeleteStorefrontImageVariants $deleteVariants,
        private readonly ApplyPreviewWatermark $watermark,
    ) {}

    /**
     * @param  array{
     *     attributes: array{sku: string},
     *     source_asset: string,
     *     parameters: LutTransformParameters
     * }  $entry
     */
    public function handle(Product $product, array $entry): ProductExample
    {
        $sourcePath = base_path($entry['source_asset']);
        $parameters = $entry['parameters'];

        if (! is_file($sourcePath)) {
            throw new RuntimeException("Storefront preview source asset is missing: {$entry['source_asset']}");
        }

        $dimensions = getimagesize($sourcePath);

        if ($dimensions === false) {
            throw new RuntimeException("Storefront preview source asset is invalid: {$entry['source_asset']}");
        }

        $sourceSha256 = hash_file('sha256', $sourcePath);

        if ($sourceSha256 === false) {
            throw new RuntimeException('Unable to fingerprint the storefront preview source asset.');
        }

        $title = 'Original vs '.$product->name;
        $fingerprint = $this->fingerprint($entry['attributes']['sku'], $sourceSha256, $parameters);
        $example = ProductExample::query()
            ->with('variants')
            ->whereBelongsTo($product)
            ->where('title', $title)
            ->first();
        $expectedVariantCountPerRole = $this->expectedVariantCountPerRole($dimensions[0]);

        if ($example instanceof ProductExample
            && $example->processing_fingerprint === $fingerprint
            && $this->hasExpectedVariants($example, $expectedVariantCountPerRole)) {
            if ($example->processing_status !== StorefrontImageStatus::Ready || $example->stale_at !== null) {
                $example->forceFill([
                    'processing_status' => StorefrontImageStatus::Ready,
                    'failure_code' => null,
                    'failure_message' => null,
                    'stale_at' => null,
                ])->save();
            }

            return $example->refresh()->load('variants');
        }

        $example ??= new ProductExample;
        $example->forceFill([
            'product_id' => $product->id,
            'title' => $title,
            'before_disk' => (string) config('storefront-media.public_disk', 'public'),
            'before_path' => '',
            'before_original_name' => basename($sourcePath),
            'before_alt_text' => $product->name.' original color',
            'after_disk' => (string) config('storefront-media.public_disk', 'public'),
            'after_path' => '',
            'after_original_name' => $product->slug.'-preview.webp',
            'after_alt_text' => $product->name.' color grade',
            'is_active' => true,
            'sort_order' => 0,
            'source_disk' => null,
            'source_path' => null,
            'source_original_name' => basename($sourcePath),
            'source_mime_type' => $dimensions['mime'],
            'source_size_bytes' => filesize($sourcePath) ?: null,
            'source_width' => $dimensions[0],
            'source_height' => $dimensions[1],
            'source_sha256' => $sourceSha256,
            'preview_product_id' => null,
            'processed_product_version_id' => null,
            'processed_product_file_id' => null,
            'processing_status' => StorefrontImageStatus::Processing,
            'pipeline_version' => config('storefront-media.pipeline_version'),
            'failure_code' => null,
            'failure_message' => null,
            'processed_at' => null,
            'stale_at' => null,
            'rights_confirmed_at' => now(),
            'rights_confirmed_by' => null,
            'rights_note' => 'Original AI-generated preview scene created for LUT Web; no third-party source asset.',
            'source_credit' => null,
            'source_license_reference' => 'LUT Web original AI-generated asset',
            'source_credit_is_public' => false,
        ])->save();

        $workDirectory = storage_path(
            'app/private/'.trim((string) config('storefront-media.temporary_work_prefix', 'storefront-work'), '/')
            .'/preview-example-'.$example->id.'-'.bin2hex(random_bytes(6)),
        );
        try {
            File::ensureDirectoryExists($workDirectory);

            if (! File::copy($sourcePath, $workDirectory.'/input.jpg')) {
                throw new RuntimeException('Unable to stage the storefront preview source asset.');
            }

            $this->writeCubeFile->handle(
                $workDirectory.'/preview.cube',
                new CubeSize(17),
                new PackageName($product->name, $product->slug),
                $parameters,
                $parameters->hash(),
            );
            $this->runFfmpeg($workDirectory);
            $this->assertMatchingDimensions($workDirectory.'/input.jpg', $workDirectory.'/graded.png');

            $beforeWatermarked = $workDirectory.'/before-watermarked.webp';
            $afterWatermarked = $workDirectory.'/after-watermarked.webp';
            $this->watermark->apply($workDirectory.'/input.jpg', $beforeWatermarked);
            $this->watermark->apply($workDirectory.'/graded.png', $afterWatermarked);

            $this->deleteVariants->deleteFor($example);
            $newVariants = $this->generateVariants
                ->handle($example, StorefrontImageVariantRole::Before, $beforeWatermarked)
                ->merge($this->generateVariants->handle(
                    $example,
                    StorefrontImageVariantRole::After,
                    $afterWatermarked,
                ));

            if ($newVariants->where('role', StorefrontImageVariantRole::Before)->count() !== $expectedVariantCountPerRole
                || $newVariants->where('role', StorefrontImageVariantRole::After)->count() !== $expectedVariantCountPerRole) {
                throw new RuntimeException('The storefront preview example has an unexpected variant count.');
            }

            $example->forceFill([
                'processing_status' => StorefrontImageStatus::Ready,
                'processing_fingerprint' => $fingerprint,
                'failure_code' => null,
                'failure_message' => null,
                'processed_at' => now(),
                'stale_at' => null,
            ])->save();

            return $example->refresh()->load('variants');
        } catch (ProcessTimedOutException $exception) {
            $this->markFailed($example, $exception);

            throw new RuntimeException('FFmpeg storefront preview generation timed out.', previous: $exception);
        } catch (Throwable $exception) {
            $this->markFailed($example, $exception);

            throw $exception;
        } finally {
            if (is_dir($workDirectory) && ! is_link($workDirectory)) {
                File::deleteDirectory($workDirectory);
            }
        }
    }

    private function runFfmpeg(string $workDirectory): void
    {
        $result = Process::path($workDirectory)
            ->timeout((int) config('storefront-media.ffmpeg_timeout', 90))
            ->run([
                (string) config('lut-tester.ffmpeg_binary', 'ffmpeg'),
                '-hide_banner',
                '-loglevel',
                'error',
                '-nostdin',
                '-y',
                '-threads',
                '1',
                '-i',
                'input.jpg',
                '-vf',
                'format=rgb24,lut3d=file=preview.cube:interp='.(string) config('storefront-media.ffmpeg_interpolation', 'tetrahedral').',format=rgb24',
                '-frames:v',
                '1',
                'graded.png',
            ]);

        if ($result->failed() || ! is_file($workDirectory.'/graded.png')) {
            throw new RuntimeException('FFmpeg storefront preview generation failed.');
        }
    }

    private function assertMatchingDimensions(string $inputPath, string $gradedPath): void
    {
        $before = getimagesize($inputPath);
        $after = getimagesize($gradedPath);

        if ($before === false || $after === false || $before[0] !== $after[0] || $before[1] !== $after[1]) {
            throw new RuntimeException('FFmpeg storefront preview output dimensions do not match.');
        }
    }

    private function fingerprint(string $sku, string $sourceSha256, LutTransformParameters $parameters): string
    {
        return hash('sha256', json_encode([
            'sku' => $sku,
            'source_sha256' => $sourceSha256,
            'parameters_sha256' => $parameters->hash(),
            'pipeline_version' => config('storefront-media.pipeline_version'),
            'responsive_widths' => array_values(config('storefront-media.responsive_widths', [])),
            'jpeg_quality' => config('storefront-media.jpeg_quality'),
            'webp_quality' => config('storefront-media.webp_quality'),
            'preview_quality' => config('lut-tester.preview_quality'),
            'watermark_text' => config('lut-tester.watermark_text'),
            'watermark_opacity' => config('lut-tester.watermark_opacity'),
            'watermark_pattern_opacity' => config('lut-tester.watermark_pattern_opacity'),
            'watermark_spacing' => config('lut-tester.watermark_spacing'),
            'ffmpeg_interpolation' => config('storefront-media.ffmpeg_interpolation'),
            'cube_size' => 17,
        ], JSON_THROW_ON_ERROR));
    }

    private function expectedVariantCountPerRole(int $sourceWidth): int
    {
        $configuredWidths = config('storefront-media.responsive_widths', []);

        if (! is_array($configuredWidths)) {
            throw new RuntimeException('Storefront responsive widths must be configured as an array.');
        }

        $widths = collect($configuredWidths)
            ->filter(fn (mixed $width): bool => is_int($width) && $width > 0)
            ->map(fn (int $width): int => min($width, $sourceWidth))
            ->unique();

        return $widths->count() * 2;
    }

    private function hasExpectedVariants(ProductExample $example, int $expectedCountPerRole): bool
    {
        return $example->variants->count() === $expectedCountPerRole * 2
            && $example->beforeVariants()->count() === $expectedCountPerRole
            && $example->afterVariants()->count() === $expectedCountPerRole
            && $example->variants->every(fn (StorefrontImageVariant $variant): bool => $variant->isPublicDerivative()
                && Storage::disk($variant->disk)->exists($variant->path));
    }

    private function markFailed(ProductExample $example, Throwable $exception): void
    {
        $this->deleteVariants->deleteFor($example);
        $example->forceFill([
            'processing_status' => StorefrontImageStatus::Failed,
            'processing_fingerprint' => null,
            'failure_code' => $exception::class,
            'failure_message' => 'We could not generate this storefront preview example.',
        ])->save();

        Log::warning('Storefront preview example generation failed.', [
            'product_example_id' => $example->id,
            'product_id' => $example->product_id,
            'failure_code' => $exception::class,
        ]);
    }
}
