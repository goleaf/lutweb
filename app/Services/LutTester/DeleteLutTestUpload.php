<?php

namespace App\Services\LutTester;

use App\Models\LutTestUpload;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteLutTestUpload
{
    public function delete(LutTestUpload $upload, bool $deleteRecord = true): bool
    {
        $disk = Storage::disk($upload->disk ?: (string) config('lut-tester.disk', 'private'));

        foreach ($this->paths($upload) as $path) {
            if ($path === null) {
                continue;
            }

            $safePath = $this->assertSafePath($path);
            $disk->delete($safePath);
            $this->deleteEmptyDirectories($disk->path($safePath));
        }

        if ($deleteRecord && $upload->exists) {
            return (bool) $upload->delete();
        }

        return true;
    }

    public function assertSafePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = trim((string) config('lut-tester.prefix', 'lut-tests'), '/').'/';

        if ($path === '' || str_contains($path, '../') || str_contains($path, '/..') || ! str_starts_with($path, $prefix)) {
            throw new RuntimeException('Refusing to delete a path outside the LUT tester storage prefix.');
        }

        return $path;
    }

    /**
     * @return array<int, string|null>
     */
    private function paths(LutTestUpload $upload): array
    {
        return [
            $upload->raw_path,
            $upload->normalized_path,
            $upload->before_preview_path,
            $upload->after_preview_path,
        ];
    }

    private function deleteEmptyDirectories(string $deletedPath): void
    {
        $prefix = Storage::disk((string) config('lut-tester.disk', 'private'))->path(
            trim((string) config('lut-tester.prefix', 'lut-tests'), '/'),
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
