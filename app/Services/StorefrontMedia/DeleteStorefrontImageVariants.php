<?php

namespace App\Services\StorefrontMedia;

use App\Models\ProductExample;
use App\Models\ProductMedia;
use App\Models\StorefrontImageVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteStorefrontImageVariants
{
    /**
     * @param  list<int|string>  $exceptIds
     */
    public function deleteFor(ProductMedia|ProductExample $record, array $exceptIds = []): int
    {
        $variants = $record->variants()
            ->when($exceptIds !== [], fn ($query) => $query->whereNotIn('id', $exceptIds))
            ->get();

        return $this->delete($variants);
    }

    /**
     * @param  Collection<int, StorefrontImageVariant>  $variants
     */
    public function delete(Collection $variants): int
    {
        $deleted = 0;
        $publicDiskName = (string) config('storefront-media.public_disk', 'public');

        foreach ($variants as $variant) {
            if ($variant->disk !== $publicDiskName) {
                continue;
            }

            $this->assertControlledPath($variant->path);

            Storage::disk($publicDiskName)->delete($variant->path);
            $this->removeEmptyDirectories($variant->path);
            $variant->delete();
            $deleted++;
        }

        return $deleted;
    }

    public function assertControlledPath(string $path): void
    {
        $prefix = trim((string) config('storefront-media.public_prefix', 'storefront'), '/').'/';
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if ($normalized === '' || str_contains($normalized, '..') || ! str_starts_with($normalized, $prefix)) {
            throw new RuntimeException('Refusing to delete a storefront path outside the controlled public prefix.');
        }
    }

    private function removeEmptyDirectories(string $path): void
    {
        $disk = Storage::disk((string) config('storefront-media.public_disk', 'public'));

        try {
            $directory = dirname($disk->path($path));
            $root = rtrim($disk->path(trim((string) config('storefront-media.public_prefix', 'storefront'), '/')), DIRECTORY_SEPARATOR);

            while (is_dir($directory) && str_starts_with($directory, $root) && $directory !== $root) {
                if (is_link($directory) || count(scandir($directory) ?: []) > 2) {
                    return;
                }

                File::deleteDirectory($directory);
                $directory = dirname($directory);
            }
        } catch (\Throwable) {
            return;
        }
    }
}
