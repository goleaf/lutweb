<?php

namespace App\Services\LutWizard;

use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteWizardProject
{
    public function __construct(private readonly DeleteWizardProjectPhoto $deletePhoto) {}

    public function delete(WizardProject $project): bool
    {
        return DB::transaction(function () use ($project): bool {
            $project->photos()
                ->get()
                ->each(fn (WizardProjectPhoto $photo): bool => $this->deletePhoto->delete($photo));

            $project->variants()->delete();

            $project->customLutBuilds()
                ->with(['files', 'orderItems', 'entitlements'])
                ->get()
                ->each(fn (CustomLutBuild $build): bool => $this->deleteOrRetainBuild($build));

            return (bool) $project->delete();
        });
    }

    private function deleteOrRetainBuild(CustomLutBuild $build): bool
    {
        if (! $build->mayBeDeleted()) {
            $build->forceFill(['wizard_project_id' => null])->save();

            return true;
        }

        $build->files->each(fn (CustomLutBuildFile $file): bool => $this->deleteBuildFile($file));

        return (bool) $build->delete();
    }

    private function deleteBuildFile(CustomLutBuildFile $file): bool
    {
        $diskName = $file->disk ?: (string) config('custom-lut-commerce.private_disk', 'private');
        $path = $this->assertSafeBuildPath($file->path);
        $disk = Storage::disk($diskName);

        $disk->delete($path);

        return (bool) $file->delete();
    }

    private function assertSafeBuildPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = trim((string) config('custom-lut-commerce.build_prefix', 'custom-lut-builds'), '/').'/';

        if ($path === '' || str_contains($path, '../') || str_contains($path, '/..') || ! str_starts_with($path, $prefix)) {
            throw new RuntimeException('Refusing to delete a path outside the Custom LUT build storage prefix.');
        }

        return $path;
    }
}
