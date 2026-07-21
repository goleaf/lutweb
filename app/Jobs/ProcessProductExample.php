<?php

namespace App\Jobs;

use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Models\ProductExample;
use App\Models\StorefrontImageVariant;
use App\Services\LutTester\ApplyPreviewWatermark;
use App\Services\StorefrontMedia\BuildStorefrontImageFingerprint;
use App\Services\StorefrontMedia\DeleteStorefrontImageVariants;
use App\Services\StorefrontMedia\GenerateStorefrontImageVariants;
use App\Services\StorefrontMedia\NormalizeStorefrontSource;
use App\Services\StorefrontMedia\ResolveProductExamplePreviewLut;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessProductExample implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public bool $failOnTimeout = true;

    public int $timeout;

    public int $tries;

    public function __construct(
        public ProductExample $productExample,
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
        return [(new WithoutOverlapping('storefront-example:'.$this->productExample->id))->expireAfter($this->timeout + 60)];
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
        ResolveProductExamplePreviewLut $resolvePreviewLut,
        BuildStorefrontImageFingerprint $fingerprints,
        GenerateStorefrontImageVariants $variants,
        ApplyPreviewWatermark $watermark,
        DeleteStorefrontImageVariants $deleteVariants,
    ): void {
        $example = ProductExample::query()->with(['variants', 'product'])->find($this->productExample->id);

        if (! $example instanceof ProductExample) {
            return;
        }

        $workDir = storage_path('app/private/'.trim((string) config('storefront-media.temporary_work_prefix', 'storefront-work'), '/').'/example-'.$example->id.'-'.bin2hex(random_bytes(6)));
        $newVariantIds = [];

        try {
            File::ensureDirectoryExists($workDir);
            $source = $normalize->handle($example);
            $lut = $resolvePreviewLut->resolve($example);
            $fingerprint = $fingerprints->productExample($example->refresh(), $lut->version, $lut->file);

            if ($example->processing_status === StorefrontImageStatus::Ready
                && $example->processing_fingerprint === $fingerprint
                && $example->variants->contains(fn ($variant): bool => $variant->isPublicDerivative())) {
                return;
            }

            $example->forceFill(['processing_status' => StorefrontImageStatus::Processing])->save();

            $this->copyStorageFile($source->disk, $source->path, $workDir.'/input.png');
            File::copy($lut->absolutePath, $workDir.'/lut.cube');
            $this->runFfmpeg($workDir);

            $this->assertMatchingDimensions($workDir.'/input.png', $workDir.'/graded.png');

            $beforeWatermarked = $workDir.'/before-watermarked.webp';
            $afterWatermarked = $workDir.'/after-watermarked.webp';
            $watermark->apply($workDir.'/input.png', $beforeWatermarked);
            $watermark->apply($workDir.'/graded.png', $afterWatermarked);

            $newVariants = $variants->handle($example, StorefrontImageVariantRole::Before, $beforeWatermarked)
                ->merge($variants->handle($example, StorefrontImageVariantRole::After, $afterWatermarked));
            foreach ($newVariants as $variant) {
                $newVariantIds[] = $variant->id;
            }

            DB::transaction(function () use ($example, $lut, $fingerprint): void {
                $example->forceFill([
                    'processed_product_version_id' => $lut->version->id,
                    'processed_product_file_id' => $lut->file->id,
                    'processing_status' => StorefrontImageStatus::Ready,
                    'pipeline_version' => config('storefront-media.pipeline_version'),
                    'processing_fingerprint' => $fingerprint,
                    'failure_code' => null,
                    'failure_message' => null,
                    'processed_at' => now(),
                    'stale_at' => null,
                ])->save();
            });

            DB::afterCommit(fn (): int => $deleteVariants->deleteFor($example, $newVariantIds));
        } catch (ProcessTimedOutException $exception) {
            $this->failExample($example, $exception, $deleteVariants, $newVariantIds);

            throw new RuntimeException('FFmpeg processing timed out.', previous: $exception);
        } catch (Throwable $exception) {
            $this->failExample($example, $exception, $deleteVariants, $newVariantIds);

            throw $exception;
        } finally {
            if (is_dir($workDir) && ! is_link($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    /**
     * @return list<string>
     */
    public function command(): array
    {
        return [
            (string) config('lut-tester.ffmpeg_binary', 'ffmpeg'),
            '-hide_banner',
            '-loglevel',
            'error',
            '-nostdin',
            '-y',
            '-threads',
            '1',
            '-i',
            'input.png',
            '-vf',
            'format=rgb24,lut3d=file=lut.cube:interp='.(string) config('storefront-media.ffmpeg_interpolation', 'tetrahedral').',format=rgb24',
            '-frames:v',
            '1',
            'graded.png',
        ];
    }

    private function runFfmpeg(string $workDir): void
    {
        $result = Process::path($workDir)
            ->timeout((int) config('storefront-media.ffmpeg_timeout', 90))
            ->run($this->command());

        if ($result->failed() || ! is_file($workDir.'/graded.png')) {
            throw new RuntimeException('FFmpeg storefront example processing failed.');
        }
    }

    private function assertMatchingDimensions(string $input, string $graded): void
    {
        $before = getimagesize($input);
        $after = getimagesize($graded);

        if (! $before || ! $after || $before[0] !== $after[0] || $before[1] !== $after[1]) {
            throw new RuntimeException('FFmpeg storefront example output dimensions do not match.');
        }
    }

    private function copyStorageFile(string $disk, string $sourcePath, string $destinationPath): void
    {
        $stream = Storage::disk($disk)->readStream($sourcePath);

        if ($stream === null) {
            throw new RuntimeException('Unable to open storefront source image.');
        }

        $target = fopen($destinationPath, 'wb');

        if ($target === false) {
            fclose($stream);
            throw new RuntimeException('Unable to create storefront work file.');
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($stream);
            fclose($target);
        }
    }

    /**
     * @param  list<int|string>  $newVariantIds
     */
    private function failExample(ProductExample $example, Throwable $exception, DeleteStorefrontImageVariants $deleteVariants, array $newVariantIds): void
    {
        foreach ($newVariantIds as $variantId) {
            $variant = StorefrontImageVariant::query()->find($variantId);

            if ($variant !== null) {
                $deleteVariants->delete(collect([$variant]));
            }
        }

        $example->forceFill([
            'processing_status' => StorefrontImageStatus::Failed,
            'failure_code' => $exception::class,
            'failure_message' => 'We could not generate this storefront example.',
        ])->save();

        Log::warning('Storefront product example processing failed.', [
            'product_example_id' => $example->id,
            'product_id' => $example->product_id,
            'failure_code' => $exception::class,
        ]);
    }
}
