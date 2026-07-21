<?php

namespace App\Services\StorefrontMedia;

use App\Models\ProductExample;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;

class NormalizeStorefrontSource
{
    public function handle(ProductMedia|ProductExample $record): NormalizedStorefrontSource
    {
        if (! $record->hasConfirmedUsageRights()) {
            throw new RuntimeException('Image usage rights must be confirmed before processing.');
        }

        $diskName = $record->source_disk ?: (string) config('storefront-media.private_disk', 'private');
        $sourcePath = $record->source_path;

        if (! is_string($sourcePath) || $sourcePath === '') {
            throw new RuntimeException('Storefront source image is missing.');
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($sourcePath)) {
            throw new RuntimeException('Storefront source image is not available.');
        }

        $absoluteSourcePath = $disk->path($sourcePath);
        $this->validateSourceFile($diskName, $sourcePath, $absoluteSourcePath, $record->source_original_name);

        $image = $this->imageManager()->decodePath($absoluteSourcePath);

        if ($image->isAnimated() || $image->count() !== 1) {
            throw new RuntimeException('Animated images are not supported.');
        }

        $this->validateDimensions($image->width(), $image->height());

        $image->orient();
        $image->fillTransparentAreas('#ffffff');

        try {
            $image->setColorspace('rgb');
        } catch (\Throwable) {
            // GD has limited ICC support; PNG re-encoding still strips unsafe metadata.
        }

        $image->scaleDown((int) config('storefront-media.normalized_master_max_edge', 2400), (int) config('storefront-media.normalized_master_max_edge', 2400));
        $image->removeProfile();

        $normalizedPath = $this->normalizedPath($record);
        $temporaryPath = $normalizedPath.'.tmp-'.bin2hex(random_bytes(6));
        $encoded = $image->encode(new PngEncoder);
        $disk->put($temporaryPath, (string) $encoded);

        if (! $disk->exists($temporaryPath)) {
            throw new RuntimeException('Normalized storefront source could not be stored.');
        }

        $disk->move($temporaryPath, $normalizedPath);

        if ($sourcePath !== $normalizedPath) {
            $disk->delete($sourcePath);
        }

        $sizeBytes = $disk->size($normalizedPath);
        $sha256 = hash('sha256', (string) $disk->get($normalizedPath));

        $record->forceFill([
            'source_disk' => $diskName,
            'source_path' => $normalizedPath,
            'source_mime_type' => 'image/png',
            'source_size_bytes' => $sizeBytes,
            'source_width' => $image->width(),
            'source_height' => $image->height(),
            'source_sha256' => $sha256,
        ])->save();

        return new NormalizedStorefrontSource(
            disk: $diskName,
            path: $normalizedPath,
            mimeType: 'image/png',
            sizeBytes: $sizeBytes,
            width: $image->width(),
            height: $image->height(),
            sha256: $sha256,
        );
    }

    private function validateDimensions(int $width, int $height): void
    {
        $minWidth = (int) config('storefront-media.minimum_width', 480);
        $minHeight = (int) config('storefront-media.minimum_height', 480);
        $maxEdge = (int) config('storefront-media.maximum_edge', 14_000);
        $maxPixels = (int) config('storefront-media.maximum_pixels', 60_000_000);

        if ($width < $minWidth || $height < $minHeight) {
            throw new RuntimeException('Source image is smaller than the configured minimum.');
        }

        if ($width > $maxEdge || $height > $maxEdge || ($width * $height) > $maxPixels) {
            throw new RuntimeException('Source image exceeds the configured size limits.');
        }
    }

    private function validateSourceFile(string $diskName, string $sourcePath, string $absoluteSourcePath, ?string $originalName): void
    {
        $disk = Storage::disk($diskName);
        $sizeBytes = $disk->size($sourcePath);
        $maximumBytes = (int) config('storefront-media.maximum_upload_bytes', 30 * 1024 * 1024);

        if ($sizeBytes > $maximumBytes) {
            throw new RuntimeException('Source image exceeds the configured upload size limit.');
        }

        $detectedMime = @mime_content_type($absoluteSourcePath);
        $acceptedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (! is_string($detectedMime) || ! in_array($detectedMime, $acceptedMimeTypes, true)) {
            throw new RuntimeException('Source image type is not supported.');
        }

        $candidateName = $originalName ?: $sourcePath;
        $extension = strtolower(pathinfo($candidateName, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Source image extension is not supported.');
        }
    }

    private function normalizedPath(Model $record): string
    {
        $type = $record instanceof ProductMedia ? 'media' : 'examples';
        $productId = (int) $record->getAttribute('product_id');

        return trim((string) config('storefront-media.private_source_prefix', 'storefront-sources'), '/')
            .'/'.$productId.'/'.$type.'/'.$record->getKey().'/source.png';
    }

    private function imageManager(): ImageManagerInterface
    {
        $driver = strcasecmp((string) config('lut-tester.image_driver', 'gd'), 'imagick') === 0
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;

        return ImageManager::usingDriver($driver);
    }
}
