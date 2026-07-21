<?php

namespace App\Services\StorefrontMedia;

use App\Models\ProductMedia;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteProductMediaFiles
{
    public function __construct(
        private readonly DeleteStorefrontImageVariants $variants,
    ) {}

    public function delete(ProductMedia $media): void
    {
        $this->deleteSource($media->source_disk, $media->source_path);
        $this->variants->deleteFor($media);
        $media->delete();
    }

    private function deleteSource(?string $disk, ?string $path): void
    {
        if (! is_string($disk) || ! is_string($path) || $path === '') {
            return;
        }

        if ($disk !== (string) config('storefront-media.private_disk', 'private')) {
            return;
        }

        $prefix = trim((string) config('storefront-media.private_source_prefix', 'storefront-sources'), '/').'/';
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (str_contains($normalized, '..') || ! str_starts_with($normalized, $prefix)) {
            throw new RuntimeException('Refusing to delete a storefront source outside the controlled private prefix.');
        }

        Storage::disk($disk)->delete($path);
    }
}
