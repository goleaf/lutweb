<?php

namespace App\Jobs;

use App\Enums\CustomLutBuildStatus;
use App\Models\CustomLutBuild;
use App\Models\WizardProject;
use App\Services\CustomLutBuilds\CustomLutBuildPurchaseEligibility;
use App\Services\CustomLutBuilds\DeleteCustomLutBuild;
use App\Services\CustomLutBuilds\GenerateCustomLutPackage;
use App\Services\CustomLutBuilds\LocalPackageFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateCustomLutBuild implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public int $tries = 2;

    public function __construct(public readonly string $buildId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('custom-lut-build:'.$this->buildId))->expireAfter(300),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(
        GenerateCustomLutPackage $packageGenerator,
        DeleteCustomLutBuild $deleteBuild,
        CustomLutBuildPurchaseEligibility $readiness,
    ): void {
        $build = CustomLutBuild::query()->find($this->buildId);

        if (! $build instanceof CustomLutBuild || $build->isReady() || $build->isSuperseded() || $build->isExpired()) {
            return;
        }

        $this->transitionToProcessing($build);
        $workDir = $this->workDirectory($build);

        try {
            File::ensureDirectoryExists($workDir);
            $package = $packageGenerator->handle($build->fresh(), $workDir);

            DB::transaction(function () use ($build, $package, $deleteBuild, $readiness): void {
                $lockedBuild = CustomLutBuild::query()
                    ->with(['files', 'wizardProject'])
                    ->whereKey($build->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedBuild->isReady() || $lockedBuild->isSuperseded() || $lockedBuild->isExpired()) {
                    return;
                }

                $project = $lockedBuild->wizardProject;

                if (! $project instanceof WizardProject || $project->revision !== $lockedBuild->project_revision || $project->parameters_hash !== $lockedBuild->parameters_hash || $project->name !== $lockedBuild->project_name_snapshot) {
                    $lockedBuild->forceFill([
                        'status' => CustomLutBuildStatus::Superseded,
                        'is_current' => false,
                        'sale_ready' => false,
                        'superseded_at' => now(),
                        'failure_code' => 'project_changed',
                        'failure_message' => 'Your project changed while the package was being prepared. Please prepare it again.',
                    ])->save();

                    return;
                }

                $deleteBuild->deleteFiles($lockedBuild);
                $this->storeGeneratedFiles($lockedBuild, $package->files);

                CustomLutBuild::query()
                    ->where('wizard_project_id', $lockedBuild->wizard_project_id)
                    ->whereKeyNot($lockedBuild->id)
                    ->whereIn('status', [
                        CustomLutBuildStatus::Queued->value,
                        CustomLutBuildStatus::Processing->value,
                        CustomLutBuildStatus::Ready->value,
                    ])
                    ->whereNull('locked_at')
                    ->update([
                        'status' => CustomLutBuildStatus::Superseded->value,
                        'is_current' => false,
                        'sale_ready' => false,
                        'superseded_at' => now(),
                    ]);

                $lockedBuild->refresh()->load(['files', 'wizardProject']);

                $lockedBuild->forceFill([
                    'status' => CustomLutBuildStatus::Ready,
                    'is_current' => true,
                    'contains_draft_documents' => $package->documents->containsDraftDocuments(),
                    'zip_validation_completed' => true,
                    'parity_validation_passed' => true,
                    'ffmpeg_validation_passed' => (bool) config('custom-lut-builds.ffmpeg_validation_enabled', true),
                    'parity_mean_error_millionths' => $package->parityMetrics->meanMillionths,
                    'parity_p95_error_millionths' => $package->parityMetrics->p95Millionths,
                    'parity_p99_error_millionths' => $package->parityMetrics->p99Millionths,
                    'parity_max_error_millionths' => $package->parityMetrics->maxMillionths,
                    'zip_size_bytes' => $package->zip->sizeBytes(),
                    'zip_sha256' => $package->zip->sha256(),
                    'uncompressed_size_bytes' => $package->uncompressedSizeBytes,
                    'failure_code' => null,
                    'failure_message' => null,
                    'prepared_at' => now(),
                    'completed_at' => now(),
                ])->save();

                $lockedBuild->refresh()->load(['files', 'wizardProject']);
                $lockedBuild->forceFill([
                    'sale_ready' => $readiness->isSaleReady($lockedBuild),
                ])->save();
            }, attempts: 3);
        } catch (Throwable $exception) {
            Log::warning('Custom LUT build generation failed.', [
                'custom_lut_build_id' => $this->buildId,
                'failure_code' => $this->failureCode($exception),
            ]);

            $this->markFailed($build, $this->failureCode($exception));
        } finally {
            $this->deleteWorkDirectory($workDir);
        }
    }

    private function transitionToProcessing(CustomLutBuild $build): void
    {
        DB::transaction(function () use ($build): void {
            $lockedBuild = CustomLutBuild::query()
                ->whereKey($build->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedBuild->isQueued()) {
                return;
            }

            $lockedBuild->forceFill([
                'status' => CustomLutBuildStatus::Processing,
                'started_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ])->save();
        });
    }

    /**
     * @param  list<LocalPackageFile>  $files
     */
    private function storeGeneratedFiles(CustomLutBuild $build, array $files): void
    {
        foreach ($files as $file) {
            $storagePath = $this->storagePath($build, $file);
            $stream = fopen($file->localPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open generated package file.');
            }

            try {
                Storage::disk((string) config('custom-lut-builds.private_disk', 'private'))->put($storagePath, $stream);
            } finally {
                fclose($stream);
            }

            $build->files()->create([
                'kind' => $file->kind,
                'disk' => (string) config('custom-lut-builds.private_disk', 'private'),
                'path' => $storagePath,
                'relative_package_path' => $file->relativePackagePath,
                'safe_download_name' => $file->safeDownloadName,
                'original_name' => $file->safeDownloadName,
                'mime_type' => $file->mimeType,
                'size_bytes' => $file->sizeBytes(),
                'sha256' => $file->sha256(),
                'sort_order' => $file->sortOrder,
            ]);
        }
    }

    private function storagePath(CustomLutBuild $build, LocalPackageFile $file): string
    {
        $base = trim((string) config('custom-lut-builds.build_prefix', 'custom-lut-builds'), '/')
            .'/'.$build->user_id.'/'.$build->wizard_project_id.'/'.$build->id;

        if ($file->kind->value === 'package_zip') {
            return $base.'/package/'.$file->safeDownloadName;
        }

        return $base.'/'.$file->relativePackagePath;
    }

    private function markFailed(CustomLutBuild $build, string $failureCode): void
    {
        DB::transaction(function () use ($build, $failureCode): void {
            $lockedBuild = CustomLutBuild::query()
                ->with('files')
                ->whereKey($build->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedBuild instanceof CustomLutBuild || $lockedBuild->isReady() || $lockedBuild->isSuperseded()) {
                return;
            }

            app(DeleteCustomLutBuild::class)->deleteFiles($lockedBuild);

            $lockedBuild->forceFill([
                'status' => CustomLutBuildStatus::Failed,
                'is_current' => false,
                'sale_ready' => false,
                'failure_code' => $failureCode,
                'failure_message' => $this->customerMessage($failureCode),
                'completed_at' => now(),
            ])->save();
        });
    }

    private function failureCode(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'FFmpeg')) {
            return 'ffmpeg_validation_failed';
        }

        if (str_contains($message, 'document') || str_contains($message, 'PDF')) {
            return 'document_generation_failed';
        }

        if (str_contains($message, 'ZIP')) {
            return 'zip_generation_failed';
        }

        return 'package_generation_failed';
    }

    private function customerMessage(string $failureCode): string
    {
        return match ($failureCode) {
            'ffmpeg_validation_failed' => 'The LUT package generator is temporarily unavailable.',
            'document_generation_failed' => 'Final package documents are not configured.',
            'project_changed' => 'Your project changed while the package was being prepared. Please prepare it again.',
            default => 'We could not prepare this LUT package.',
        };
    }

    private function workDirectory(CustomLutBuild $build): string
    {
        return storage_path('app/private/'.trim((string) config('custom-lut-builds.work_prefix', 'custom-lut-build-work'), '/').'/'.$build->id.'-'.bin2hex(random_bytes(6)));
    }

    private function deleteWorkDirectory(string $workDir): void
    {
        $root = storage_path('app/private/'.trim((string) config('custom-lut-builds.work_prefix', 'custom-lut-build-work'), '/'));

        if (is_dir($workDir) && str_starts_with($workDir, $root.'/') && ! is_link($workDir)) {
            File::deleteDirectory($workDir);
        }
    }
}
