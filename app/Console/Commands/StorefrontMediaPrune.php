<?php

namespace App\Console\Commands;

use App\Models\StorefrontImageVariant;
use App\Services\StorefrontMedia\DeleteStorefrontImageVariants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('storefront-media:prune {--dry-run} {--limit=500}')]
#[Description('Prune orphaned and temporary storefront media derivatives.')]
class StorefrontMediaPrune extends Command
{
    public function handle(DeleteStorefrontImageVariants $deleteVariants): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $diskName = (string) config('storefront-media.public_disk', 'public');
        $prefix = trim((string) config('storefront-media.public_prefix', 'storefront'), '/');
        $disk = Storage::disk($diskName);
        $knownPaths = StorefrontImageVariant::query()
            ->select(['path'])
            ->pluck('path')
            ->all();
        $knownLookup = array_fill_keys($knownPaths, true);
        $scanned = 0;
        $deleted = 0;
        $temporary = 0;
        $orphaned = 0;

        foreach ($disk->allFiles($prefix) as $path) {
            if ($scanned >= $limit) {
                break;
            }

            $scanned++;
            $deleteVariants->assertControlledPath($path);

            $isTemporary = str_ends_with($path, '.tmp') || str_contains($path, '/tmp/');
            $isOrphaned = ! isset($knownLookup[$path]);

            if (! $isTemporary && ! $isOrphaned) {
                continue;
            }

            $temporary += $isTemporary ? 1 : 0;
            $orphaned += $isOrphaned ? 1 : 0;

            if (! $dryRun) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->line('scanned='.$scanned);
        $this->line('temporary='.$temporary);
        $this->line('orphaned='.$orphaned);
        $this->line('deleted='.$deleted);
        $this->line('dry_run='.($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
