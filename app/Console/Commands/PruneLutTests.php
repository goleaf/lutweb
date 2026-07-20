<?php

namespace App\Console\Commands;

use App\Enums\LutTestStatus;
use App\Models\LutTestUpload;
use App\Services\LutTester\DeleteLutTestUpload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('lut-tests:prune {--dry-run} {--limit=500}')]
#[Description('Delete expired temporary LUT test uploads and stale processing work directories.')]
class PruneLutTests extends Command
{
    public function handle(DeleteLutTestUpload $deleteLutTestUpload): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $deleted = 0;
        $marked = 0;
        $errors = 0;

        $uploads = LutTestUpload::query()
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($uploads as $upload) {
            try {
                if (! $dryRun && $upload->status !== LutTestStatus::Expired) {
                    $upload->forceFill(['status' => LutTestStatus::Expired])->save();
                    $marked++;
                }

                if (! $dryRun) {
                    $deleteLutTestUpload->delete($upload);
                }

                $deleted++;
            } catch (\Throwable $exception) {
                $errors++;
                $this->warn('Could not prune LUT test '.$upload->id.'.');
            }
        }

        $workDirectoriesDeleted = $this->cleanStaleWorkDirectories($dryRun);

        $this->info(($dryRun ? 'Dry run: ' : '').'expired records matched: '.$uploads->count());
        $this->info('records marked expired: '.$marked);
        $this->info('records deleted: '.$deleted);
        $this->info('stale work directories deleted: '.$workDirectoriesDeleted);

        if ($errors > 0) {
            $this->error('Prune finished with '.$errors.' error(s).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function cleanStaleWorkDirectories(bool $dryRun): int
    {
        $root = storage_path('app/private/'.trim((string) config('lut-tester.work_prefix', 'lut-tests-work'), '/'));

        if (! is_dir($root)) {
            return 0;
        }

        $deleted = 0;
        $cutoff = now()->subHours(2)->getTimestamp();

        foreach (File::directories($root) as $directory) {
            if (is_link($directory) || filemtime($directory) === false || filemtime($directory) > $cutoff) {
                continue;
            }

            if (! $dryRun) {
                File::deleteDirectory($directory);
            }

            $deleted++;
        }

        return $deleted;
    }
}
