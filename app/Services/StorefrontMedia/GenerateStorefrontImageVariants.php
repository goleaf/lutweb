<?php

namespace App\Services\StorefrontMedia;

use App\Enums\StorefrontImageFormat;
use App\Enums\StorefrontImageVariantRole;
use App\Models\ProductExample;
use App\Models\ProductMedia;
use App\Models\StorefrontImageVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;

class GenerateStorefrontImageVariants
{
    /**
     * @return Collection<int, StorefrontImageVariant>
     */
    public function handle(ProductMedia|ProductExample $record, StorefrontImageVariantRole $role, string $localInputPath, bool $watermark = false): Collection
    {
        $manager = $this->imageManager();
        $baseImage = $manager->decodePath($localInputPath);

        if ($baseImage->isAnimated() || $baseImage->count() !== 1) {
            throw new RuntimeException('Generated source is not a still image.');
        }

        $widths = collect($this->responsiveWidths($baseImage->width()));

        if ($widths->isEmpty()) {
            throw new RuntimeException('No valid storefront responsive widths are configured.');
        }

        $created = collect();

        foreach ($widths as $width) {
            foreach ([StorefrontImageFormat::Jpeg, StorefrontImageFormat::Webp] as $format) {
                $image = $manager->decodePath($localInputPath);
                $image->scaleDown($width, $width);
                $image->removeProfile();

                $encoded = $format === StorefrontImageFormat::Webp
                    ? $image->encode(new WebpEncoder((int) config('storefront-media.webp_quality', 82), strip: true))
                    : $image->encode(new JpegEncoder((int) config('storefront-media.jpeg_quality', 84), strip: true));

                $bytes = (string) $encoded;
                $sha256 = hash('sha256', $bytes);
                $path = $this->path($record, $role, $format, $image->width(), $sha256);
                $diskName = (string) config('storefront-media.public_disk', 'public');
                Storage::disk($diskName)->put($path.'.tmp', $bytes);
                Storage::disk($diskName)->move($path.'.tmp', $path);

                $created->push(StorefrontImageVariant::query()->create([
                    'imageable_type' => $record->getMorphClass(),
                    'imageable_id' => $record->getKey(),
                    'role' => $role,
                    'format' => $format,
                    'disk' => $diskName,
                    'path' => $path,
                    'mime_type' => $format === StorefrontImageFormat::Webp ? 'image/webp' : 'image/jpeg',
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'quality' => $format === StorefrontImageFormat::Webp ? (int) config('storefront-media.webp_quality', 82) : (int) config('storefront-media.jpeg_quality', 84),
                    'size_bytes' => strlen($bytes),
                    'sha256' => $sha256,
                    'generated_at' => now(),
                ]));
            }
        }

        return $created;
    }

    private function path(ProductMedia|ProductExample $record, StorefrontImageVariantRole $role, StorefrontImageFormat $format, int $width, string $sha256): string
    {
        $type = $record instanceof ProductMedia ? 'media' : 'examples';

        return trim((string) config('storefront-media.public_prefix', 'storefront'), '/')
            .'/'.$type.'/'.$record->getKey().'/'.$role->value.'/'.$width.'-'.$sha256.'.'.$format->value;
    }

    /**
     * @return list<int>
     */
    private function responsiveWidths(int $maximumWidth): array
    {
        $configuredWidths = config('storefront-media.responsive_widths', []);

        if (! is_array($configuredWidths)) {
            return [];
        }

        $widths = [];

        foreach ($configuredWidths as $width) {
            if (is_int($width) && $width > 0) {
                $widths[] = min($width, $maximumWidth);
            }
        }

        $widths = array_values(array_unique($widths));
        sort($widths);

        return $widths;
    }

    private function imageManager(): ImageManagerInterface
    {
        $driver = strcasecmp((string) config('lut-tester.image_driver', 'gd'), 'imagick') === 0
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;

        return ImageManager::usingDriver($driver);
    }
}
