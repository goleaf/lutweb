<?php

namespace App\Jobs;

use App\Enums\WizardPhotoStatus;
use App\Models\WizardProjectPhoto;
use App\Services\LutWizard\DeleteWizardProjectPhoto;
use App\Services\LutWizard\RenderWizardProjectPhotoPreview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessWizardProjectPhoto implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(
        public WizardProjectPhoto $photo,
    ) {
        $this->onQueue((string) config('lut-wizard.queue_name', 'images'));
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('wizard-project-photo:'.$this->photo->id))
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

    public function handle(RenderWizardProjectPhotoPreview $renderer): void
    {
        $photo = $this->transitionToProcessing();

        if (! $photo instanceof WizardProjectPhoto) {
            return;
        }

        try {
            $preview = $renderer->render($photo);
            $photo->refresh();

            if ($photo->raw_path !== null) {
                Storage::disk($photo->disk)->delete($photo->raw_path);
            }

            $photo->forceFill([
                'status' => WizardPhotoStatus::Ready,
                'raw_path' => null,
                'preview_path' => $preview['path'],
                'preview_mime_type' => 'image/webp',
                'preview_width' => $preview['width'],
                'preview_height' => $preview['height'],
                'failure_code' => null,
                'failure_message' => null,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $photo->refresh();
            $this->cleanupPartialFiles($photo, finalFailure: $this->attempts() >= $this->tries);

            if ($this->attempts() < $this->tries) {
                $photo->forceFill([
                    'status' => WizardPhotoStatus::Queued,
                    'failure_code' => null,
                    'failure_message' => null,
                ])->save();

                throw $exception;
            }

            $this->markFailed($photo, $exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $photo = WizardProjectPhoto::query()->find($this->photo->id);

        if (! $photo instanceof WizardProjectPhoto || $photo->isReady()) {
            return;
        }

        $this->cleanupPartialFiles($photo, finalFailure: true);
        $this->markFailed($photo, $exception ?? new RuntimeException('The processing job failed.'));
    }

    private function transitionToProcessing(): ?WizardProjectPhoto
    {
        return DB::transaction(function (): ?WizardProjectPhoto {
            $photo = WizardProjectPhoto::query()
                ->whereKey($this->photo->id)
                ->lockForUpdate()
                ->first();

            if (! $photo instanceof WizardProjectPhoto) {
                return null;
            }

            if ($photo->status === WizardPhotoStatus::Ready || $photo->status === WizardPhotoStatus::Expired) {
                return null;
            }

            if ($photo->isExpired()) {
                $photo->forceFill(['status' => WizardPhotoStatus::Expired])->save();

                return null;
            }

            $photo->forceFill(['status' => WizardPhotoStatus::Processing])->save();

            return $photo;
        });
    }

    private function cleanupPartialFiles(WizardProjectPhoto $photo, bool $finalFailure): void
    {
        $disk = Storage::disk($photo->disk);

        if ($photo->preview_path !== null) {
            app(DeleteWizardProjectPhoto::class)->assertSafePath($photo->preview_path);
            $disk->delete($photo->preview_path);
            $photo->preview_path = null;
        }

        if ($finalFailure && $photo->raw_path !== null) {
            app(DeleteWizardProjectPhoto::class)->assertSafePath($photo->raw_path);
            $disk->delete($photo->raw_path);
            $photo->raw_path = null;
        }

        $photo->save();
    }

    private function markFailed(WizardProjectPhoto $photo, Throwable $exception): void
    {
        Log::warning('Custom LUT Wizard photo processing failed.', [
            'wizard_project_photo_id' => $photo->id,
            'failure' => $exception::class,
        ]);

        $photo->forceFill([
            'status' => WizardPhotoStatus::Failed,
            'failure_code' => 'processing_failed',
            'failure_message' => 'We could not process this photo.',
            'completed_at' => now(),
        ])->save();
    }
}
