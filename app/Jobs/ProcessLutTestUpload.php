<?php

namespace App\Jobs;

use App\Enums\LutTestStatus;
use App\Models\LutTestUpload;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Services\LutTester\ApplyCubeLutWithFfmpeg;
use App\Services\LutTester\InspectCubeFile;
use App\Services\LutTester\NormalizeUploadedPhoto;
use App\Services\LutTester\ResolvedPreviewLut;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessLutTestUpload implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(
        public LutTestUpload $lutTestUpload,
    ) {
        $this->onQueue((string) config('lut-tester.queue', 'images'));
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('lut-test-upload:'.$this->lutTestUpload->id))
                ->releaseAfter(30)
                ->expireAfter(180),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(
        NormalizeUploadedPhoto $normalizeUploadedPhoto,
        InspectCubeFile $inspectCubeFile,
        ApplyCubeLutWithFfmpeg $applyCubeLut,
    ): void {
        $upload = $this->transitionToProcessing();

        if (! $upload instanceof LutTestUpload) {
            return;
        }

        try {
            $lut = $this->resolvedRecordedLut($upload, $inspectCubeFile);
            $normalizeUploadedPhoto->handle($upload);
            $upload->refresh();
            $applyCubeLut->handle($upload, $lut);
            $upload->refresh();

            $upload->forceFill([
                'status' => LutTestStatus::Ready,
                'failure_code' => null,
                'failure_message' => null,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $upload->refresh();
            $this->cleanupPartialFiles($upload, finalFailure: $this->attempts() >= $this->tries);

            if ($this->attempts() < $this->tries) {
                $upload->forceFill([
                    'status' => LutTestStatus::Queued,
                    'failure_code' => null,
                    'failure_message' => null,
                ])->save();

                throw $exception;
            }

            $this->markFailed($upload, $exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $upload = LutTestUpload::query()->find($this->lutTestUpload->id);

        if (! $upload instanceof LutTestUpload || $upload->isReady()) {
            return;
        }

        $this->cleanupPartialFiles($upload, finalFailure: true);
        $this->markFailed($upload, $exception ?? new RuntimeException('The processing job failed.'));
    }

    private function transitionToProcessing(): ?LutTestUpload
    {
        return DB::transaction(function (): ?LutTestUpload {
            $upload = LutTestUpload::query()
                ->whereKey($this->lutTestUpload->id)
                ->lockForUpdate()
                ->first();

            if (! $upload instanceof LutTestUpload) {
                return null;
            }

            if ($upload->isReady() || $upload->isFailed()) {
                return null;
            }

            if ($upload->isExpired()) {
                $upload->forceFill(['status' => LutTestStatus::Expired])->save();

                return null;
            }

            $upload->forceFill(['status' => LutTestStatus::Processing])->save();

            return $upload;
        });
    }

    private function resolvedRecordedLut(LutTestUpload $upload, InspectCubeFile $inspectCubeFile): ResolvedPreviewLut
    {
        $version = ProductVersion::query()->find($upload->product_version_id);
        $file = ProductFile::query()->find($upload->product_file_id);

        if (! $version instanceof ProductVersion || ! $file instanceof ProductFile) {
            throw new RuntimeException('The selected LUT file is no longer available.');
        }

        if ($file->disk !== (string) config('lut-tester.disk', 'private')) {
            throw new RuntimeException('The selected LUT file is not stored privately.');
        }

        $inspection = $inspectCubeFile->inspect($file);

        return new ResolvedPreviewLut(
            version: $version,
            file: $file,
            absolutePath: Storage::disk($file->disk)->path($file->path),
            inspection: $inspection,
        );
    }

    private function cleanupPartialFiles(LutTestUpload $upload, bool $finalFailure): void
    {
        $disk = Storage::disk($upload->disk);
        $paths = [
            'before_preview_path',
            'after_preview_path',
        ];

        if ($finalFailure) {
            $paths[] = 'normalized_path';
            $paths[] = 'raw_path';
        }

        foreach ($paths as $field) {
            $path = $upload->{$field};

            if (is_string($path) && $path !== '') {
                $disk->delete($path);
                $upload->{$field} = null;
            }
        }

        $upload->save();
    }

    private function markFailed(LutTestUpload $upload, Throwable $exception): void
    {
        Log::warning('LUT test upload processing failed.', [
            'lut_test_upload_id' => $upload->id,
            'product_id' => $upload->product_id,
            'product_version_id' => $upload->product_version_id,
            'failure' => $exception::class,
        ]);

        $message = str_contains($exception->getMessage(), 'LUT')
            ? 'This LUT is currently unavailable for online testing.'
            : 'We could not process this image.';

        $upload->forceFill([
            'status' => LutTestStatus::Failed,
            'failure_code' => 'processing_failed',
            'failure_message' => $message,
            'completed_at' => now(),
        ])->save();
    }
}
