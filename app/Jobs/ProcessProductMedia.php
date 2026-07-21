<?php

namespace App\Jobs;

use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Models\ProductMedia;
use App\Models\StorefrontImageVariant;
use App\Services\LutTester\ApplyPreviewWatermark;
use App\Services\StorefrontMedia\BuildStorefrontImageFingerprint;
use App\Services\StorefrontMedia\DeleteStorefrontImageVariants;
use App\Services\StorefrontMedia\GenerateStorefrontImageVariants;
use App\Services\StorefrontMedia\NormalizeStorefrontSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessProductMedia implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public bool $failOnTimeout = true;

    public int $timeout;

    public int $tries;

    public function __construct(
        public ProductMedia $productMedia,
    ) {
        $this->onQueue((string) config('storefront-media.queue', 'images'));
        $this->timeout = (int) config('storefront-media.processing_timeout', 120);
        $this->tries = (int) config('storefront-media.retry_count', 2);
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('storefront-media:'.$this->productMedia->id))->expireAfter($this->timeout + 60)];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(
        NormalizeStorefrontSource $normalize,
        BuildStorefrontImageFingerprint $fingerprints,
        GenerateStorefrontImageVariants $variants,
        ApplyPreviewWatermark $watermark,
        DeleteStorefrontImageVariants $deleteVariants,
    ): void {
        $media = ProductMedia::query()->with('variants')->find($this->productMedia->id);

        if (! $media instanceof ProductMedia) {
            return;
        }

        $workDir = storage_path('app/private/'.trim((string) config('storefront-media.temporary_work_prefix', 'storefront-work'), '/').'/media-'.$media->id.'-'.bin2hex(random_bytes(6)));
        $newVariantIds = [];

        try {
            File::ensureDirectoryExists($workDir);
            $source = $normalize->handle($media);
            $fingerprint = $fingerprints->productMedia($media->refresh());

            if ($media->processing_status === StorefrontImageStatus::Ready
                && $media->processing_fingerprint === $fingerprint
                && $media->variants->contains(fn ($variant): bool => $variant->isPublicDerivative())) {
                return;
            }

            $media->forceFill(['processing_status' => StorefrontImageStatus::Processing])->save();

            $inputPath = Storage::disk($source->disk)->path($source->path);

            if ((bool) config('storefront-media.product_media_watermark_enabled', false)) {
                $watermarkedPath = $workDir.'/media-watermarked.webp';
                $watermark->apply($inputPath, $watermarkedPath);
                $inputPath = $watermarkedPath;
            }

            $newVariants = $variants->handle($media, StorefrontImageVariantRole::Media, $inputPath);
            foreach ($newVariants as $variant) {
                $newVariantIds[] = $variant->id;
            }

            DB::transaction(function () use ($media, $fingerprint): void {
                $media->forceFill([
                    'processing_status' => StorefrontImageStatus::Ready,
                    'pipeline_version' => config('storefront-media.pipeline_version'),
                    'processing_fingerprint' => $fingerprint,
                    'failure_code' => null,
                    'failure_message' => null,
                    'processed_at' => now(),
                    'stale_at' => null,
                ])->save();
            });

            DB::afterCommit(fn (): int => $deleteVariants->deleteFor($media, $newVariantIds));
        } catch (Throwable $exception) {
            foreach ($newVariantIds as $variantId) {
                $variant = StorefrontImageVariant::query()->find($variantId);

                if ($variant !== null) {
                    $deleteVariants->delete(collect([$variant]));
                }
            }

            $media->forceFill([
                'processing_status' => StorefrontImageStatus::Failed,
                'failure_code' => $exception::class,
                'failure_message' => 'We could not process this storefront image.',
            ])->save();

            Log::warning('Storefront product media processing failed.', [
                'product_media_id' => $media->id,
                'product_id' => $media->product_id,
                'failure_code' => $exception::class,
            ]);

            throw $exception;
        } finally {
            if (is_dir($workDir) && ! is_link($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }
}
