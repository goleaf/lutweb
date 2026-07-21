<?php

namespace App\Services\CustomLutBuilds;

use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteCustomLutBuild
{
    public function delete(CustomLutBuild $build, bool $deleteRecord = true): bool
    {
        return DB::transaction(function () use ($build, $deleteRecord): bool {
            $lockedBuild = CustomLutBuild::query()
                ->with(['files', 'orderItems', 'entitlements'])
                ->whereKey($build->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedBuild instanceof CustomLutBuild) {
                return true;
            }

            if (! $lockedBuild->mayBeDeleted()) {
                return false;
            }

            $this->deleteFiles($lockedBuild);

            return $deleteRecord ? (bool) $lockedBuild->delete() : true;
        });
    }

    public function deleteFiles(CustomLutBuild $build): void
    {
        $build->loadMissing('files');

        $build->files->each(function (CustomLutBuildFile $file): void {
            $path = $this->assertSafePath($file->path);
            Storage::disk($file->disk ?: (string) config('custom-lut-builds.private_disk', 'private'))->delete($path);
            $file->delete();
        });
    }

    private function assertSafePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = trim((string) config('custom-lut-builds.build_prefix', 'custom-lut-builds'), '/').'/';

        if ($path === '' || str_contains($path, '../') || str_contains($path, '/..') || str_starts_with($path, '/') || ! str_starts_with($path, $prefix)) {
            throw new RuntimeException('Refusing to delete a path outside the Custom LUT build storage prefix.');
        }

        return $path;
    }
}
