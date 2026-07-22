<?php

namespace App\Actions\Storefront;

use App\Color\CubeSize;
use App\Enums\ProductMediaKind;
use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\CustomLutBuilds\PackageName;
use App\Services\CustomLutBuilds\WriteCubeFile;
use App\Services\StorefrontMedia\DeleteStorefrontImageVariants;
use App\Services\StorefrontMedia\GenerateStorefrontImageVariants;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class GenerateStorefrontPreviewCover
{
    public function __construct(
        private readonly WriteCubeFile $writeCubeFile,
        private readonly GenerateStorefrontImageVariants $generateVariants,
        private readonly DeleteStorefrontImageVariants $deleteVariants,
    ) {}

    /**
     * @param  array{
     *     attributes: array{sku: string},
     *     source_asset: string,
     *     parameters: LutTransformParameters
     * }  $entry
     */
    public function handle(Product $product, array $entry): ProductMedia
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

        $fingerprint = $this->fingerprint($entry['attributes']['sku'], $sourceSha256, $parameters);
        $media = ProductMedia::query()
            ->with('variants')
            ->whereBelongsTo($product)
            ->where('kind', ProductMediaKind::Cover)
            ->first();
        $expectedVariantCount = $this->expectedVariantCount($dimensions[0]);

        if ($media instanceof ProductMedia
            && $media->processing_status === StorefrontImageStatus::Ready
            && $media->processing_fingerprint === $fingerprint
            && $media->variants->count() === $expectedVariantCount
            && $media->variants->every->isPublicDerivative()) {
            return $media;
        }

        $media ??= new ProductMedia;
        $media->forceFill([
            'product_id' => $product->id,
            'kind' => ProductMediaKind::Cover,
            'disk' => (string) config('storefront-media.public_disk', 'public'),
            'path' => '',
            'original_name' => basename($sourcePath),
            'alt_text' => $product->name.' color preview',
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'sort_order' => 0,
            'source_disk' => null,
            'source_path' => null,
            'source_original_name' => basename($sourcePath),
            'source_mime_type' => $dimensions['mime'],
            'source_size_bytes' => filesize($sourcePath) ?: null,
            'source_width' => $dimensions[0],
            'source_height' => $dimensions[1],
            'source_sha256' => $sourceSha256,
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
            .'/preview-cover-'.$media->id.'-'.bin2hex(random_bytes(6)),
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
            $this->deleteVariants->deleteFor($media);
            $variants = $this->generateVariants->handle(
                $media,
                StorefrontImageVariantRole::Media,
                $workDirectory.'/graded.png',
            );

            if ($variants->count() !== $expectedVariantCount) {
                throw new RuntimeException('The storefront preview cover has an unexpected variant count.');
            }

            $media->forceFill([
                'processing_status' => StorefrontImageStatus::Ready,
                'processing_fingerprint' => $fingerprint,
                'failure_code' => null,
                'failure_message' => null,
                'processed_at' => now(),
                'stale_at' => null,
            ])->save();

            return $media->refresh()->load('variants');
        } catch (ProcessTimedOutException $exception) {
            $this->markFailed($media, $exception);

            throw new RuntimeException('FFmpeg storefront preview generation timed out.', previous: $exception);
        } catch (Throwable $exception) {
            $this->markFailed($media, $exception);

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
            'ffmpeg_interpolation' => config('storefront-media.ffmpeg_interpolation'),
            'cube_size' => 17,
        ], JSON_THROW_ON_ERROR));
    }

    private function expectedVariantCount(int $sourceWidth): int
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

    private function markFailed(ProductMedia $media, Throwable $exception): void
    {
        $this->deleteVariants->deleteFor($media);
        $media->forceFill([
            'processing_status' => StorefrontImageStatus::Failed,
            'processing_fingerprint' => null,
            'failure_code' => $exception::class,
            'failure_message' => 'We could not generate this storefront preview cover.',
        ])->save();

        Log::warning('Storefront preview cover generation failed.', [
            'product_media_id' => $media->id,
            'product_id' => $media->product_id,
            'failure_code' => $exception::class,
        ]);
    }
}
