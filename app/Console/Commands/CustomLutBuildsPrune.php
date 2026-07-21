<?php

namespace App\Console\Commands;

use App\Enums\CustomLutBuildStatus;
use App\Models\CustomLutBuild;
use App\Services\CustomLutBuilds\DeleteCustomLutBuild;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('custom-lut-builds:prune {--dry-run : Show what would be pruned without deleting} {--limit=200 : Maximum builds to inspect}')]
#[Description('Prune expired or obsolete Custom LUT build package files.')]
class CustomLutBuildsPrune extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DeleteCustomLutBuild $deleteBuild): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $deleted = 0;
        $marked = 0;
        $failed = 0;

        $builds = CustomLutBuild::query()
            ->with(['files', 'orderItems', 'entitlements'])
            ->where(function ($query): void {
                $query->where('expires_at', '<=', now())
                    ->orWhereIn('status', [
                        CustomLutBuildStatus::Superseded->value,
                        CustomLutBuildStatus::Failed->value,
                    ]);
            })
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($builds as $build) {
            try {
                if ($dryRun) {
                    $this->line('Would prune build '.$build->id.' ('.$build->status->value.')');

                    continue;
                }

                if ($build->expires_at !== null && $build->expires_at->isPast() && $build->status !== CustomLutBuildStatus::Expired) {
                    $build->forceFill([
                        'status' => CustomLutBuildStatus::Expired,
                        'sale_ready' => false,
                        'is_current' => false,
                    ])->save();
                    $marked++;
                }

                if ($deleteBuild->delete($build, deleteRecord: true)) {
                    $deleted++;
                }
            } catch (Throwable) {
                $failed++;
                $this->error('Failed to prune Custom LUT build '.$build->id.'.');
            }
        }

        $staleWorkDirectories = $dryRun ? 0 : $this->cleanStaleWorkDirectories();

        $this->info("Custom LUT build prune complete: {$deleted} builds deleted, {$marked} marked expired, {$staleWorkDirectories} stale work directories removed, {$failed} failures.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function cleanStaleWorkDirectories(): int
    {
        $root = storage_path('app/private/'.trim((string) config('custom-lut-builds.work_prefix', 'custom-lut-build-work'), '/'));

        if (! is_dir($root) || is_link($root)) {
            return 0;
        }

        $count = 0;

        foreach (File::directories($root) as $directory) {
            if (is_link($directory) || ! str_starts_with($directory, $root.'/')) {
                continue;
            }

            $modifiedAt = filemtime($directory);

            if ($modifiedAt !== false && $modifiedAt < now()->subHours(2)->timestamp) {
                File::deleteDirectory($directory);
                $count++;
            }
        }

        return $count;
    }
}
