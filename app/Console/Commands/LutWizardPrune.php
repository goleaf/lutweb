<?php

namespace App\Console\Commands;

use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Services\LutWizard\DeleteWizardProject;
use App\Services\LutWizard\DeleteWizardProjectPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LutWizardPrune extends Command
{
    protected $signature = 'lut-wizard:prune {--dry-run : Report deletions without changing data} {--limit=500 : Maximum projects and photos per batch}';

    protected $description = 'Prune expired Custom LUT Wizard photos, projects, and controlled work files.';

    public function handle(DeleteWizardProjectPhoto $deletePhoto, DeleteWizardProject $deleteProject): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, min(5_000, (int) $this->option('limit')));
        $failures = 0;

        $photos = WizardProjectPhoto::query()
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($photos as $photo) {
            try {
                if (! $dryRun) {
                    $deletePhoto->delete($photo);
                }
            } catch (Throwable $exception) {
                $failures++;
                $this->warn('Failed to prune photo '.$photo->id.': '.$exception->getMessage());
            }
        }

        $projects = WizardProject::query()
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($projects as $project) {
            try {
                if (! $dryRun) {
                    $deleteProject->delete($project);
                }
            } catch (Throwable $exception) {
                $failures++;
                $this->warn('Failed to prune project '.$project->id.': '.$exception->getMessage());
            }
        }

        $workDirectories = $this->staleWorkDirectories();

        if (! $dryRun) {
            foreach ($workDirectories as $directory) {
                File::deleteDirectory($directory, false);
            }
        }

        $this->line('Expired photos: '.$photos->count().($dryRun ? ' planned' : ' deleted'));
        $this->line('Expired projects: '.$projects->count().($dryRun ? ' planned' : ' deleted'));
        $this->line('Stale work directories: '.count($workDirectories).($dryRun ? ' planned' : ' deleted'));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function staleWorkDirectories(): array
    {
        $root = Storage::disk((string) config('lut-wizard.disk', 'private'))->path(
            trim((string) config('lut-wizard.work_prefix', 'custom-lut-work'), '/'),
        );

        if (! is_dir($root) || is_link($root)) {
            return [];
        }

        $directories = [];
        $threshold = now()->subHours(2)->timestamp;

        foreach (File::directories($root) as $directory) {
            if (is_link($directory) || ! is_dir($directory)) {
                continue;
            }

            $modifiedAt = filemtime($directory);

            if ($modifiedAt !== false && $modifiedAt <= $threshold) {
                $directories[] = $directory;
            }
        }

        sort($directories);

        return $directories;
    }
}
