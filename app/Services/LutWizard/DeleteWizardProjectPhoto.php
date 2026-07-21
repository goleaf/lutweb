<?php

namespace App\Services\LutWizard;

use App\Models\WizardProjectPhoto;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteWizardProjectPhoto
{
    public function delete(WizardProjectPhoto $photo, bool $deleteRecord = true): bool
    {
        $disk = Storage::disk($photo->disk ?: (string) config('lut-wizard.disk', 'private'));

        foreach ([$photo->raw_path, $photo->preview_path] as $path) {
            if ($path === null) {
                continue;
            }

            $safePath = $this->assertSafePath($path);
            $disk->delete($safePath);
            $this->deleteEmptyDirectories($disk->path($safePath));
        }

        if ($deleteRecord && $photo->exists) {
            return (bool) $photo->delete();
        }

        return true;
    }

    public function assertSafePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = trim((string) config('lut-wizard.prefix', 'custom-lut-projects'), '/').'/';

        if ($path === '' || str_contains($path, '../') || str_contains($path, '/..') || ! str_starts_with($path, $prefix)) {
            throw new RuntimeException('Refusing to delete a path outside the Custom LUT Wizard storage prefix.');
        }

        return $path;
    }

    private function deleteEmptyDirectories(string $deletedPath): void
    {
        $prefix = Storage::disk((string) config('lut-wizard.disk', 'private'))->path(
            trim((string) config('lut-wizard.prefix', 'custom-lut-projects'), '/'),
        );
        $directory = dirname($deletedPath);

        while (str_starts_with($directory, $prefix) && $directory !== $prefix) {
            if (is_link($directory) || ! is_dir($directory)) {
                return;
            }

            $files = scandir($directory);

            if ($files === false || count(array_diff($files, ['.', '..'])) > 0) {
                return;
            }

            rmdir($directory);
            $directory = dirname($directory);
        }
    }
}
