<?php

namespace App\Services\LutTester;

use App\Models\LutTestUpload;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;

class NormalizeUploadedPhoto
{
    public function handle(LutTestUpload $upload): NormalizedPhoto
    {
        $disk = Storage::disk($upload->disk);

        if ($upload->normalized_path !== null && $disk->exists($upload->normalized_path)) {
            $image = $this->imageManager()->decodePath($disk->path($upload->normalized_path));

            return new NormalizedPhoto(
                path: $upload->normalized_path,
                width: $image->width(),
                height: $image->height(),
            );
        }

        if ($upload->raw_path === null) {
            throw new RuntimeException('Raw upload is missing.');
        }

        $rawPath = $upload->raw_path;

        if (! $disk->exists($rawPath)) {
            throw new RuntimeException('Raw upload is not available.');
        }

        $sourcePath = $disk->path($rawPath);
        $image = $this->imageManager()->decodePath($sourcePath);

        if ($image->isAnimated() || $image->count() !== 1) {
            throw new RuntimeException('Animated images are not supported.');
        }

        $image->orient();
        $image->fillTransparentAreas((string) config('lut-tester.transparency_background', '#ffffff'));

        try {
            $image->setColorspace('rgb');
        } catch (\Throwable) {
            // GD cannot provide advanced ICC handling; decoding plus PNG encoding gives a safe RGB preview source.
        }

        $maxEdge = (int) config('lut-tester.preview_max_edge', 1_920);
        $image->scaleDown($maxEdge, $maxEdge);
        $image->removeProfile();

        $normalizedPath = $this->normalizedPath($upload);
        $encoded = $image->encode(new PngEncoder);
        $disk->put($normalizedPath, (string) $encoded);

        if (! $disk->exists($normalizedPath)) {
            throw new RuntimeException('Normalized preview source could not be stored.');
        }

        $disk->delete($rawPath);

        $upload->forceFill([
            'raw_path' => null,
            'normalized_path' => $normalizedPath,
            'preview_width' => $image->width(),
            'preview_height' => $image->height(),
        ])->save();

        return new NormalizedPhoto(
            path: $normalizedPath,
            width: $image->width(),
            height: $image->height(),
        );
    }

    private function normalizedPath(LutTestUpload $upload): string
    {
        return trim((string) config('lut-tester.prefix', 'lut-tests'), '/')
            .'/'.$upload->user_id.'/'.$upload->id.'/source.png';
    }

    private function imageManager(): ImageManagerInterface
    {
        $driver = strcasecmp((string) config('lut-tester.image_driver', 'gd'), 'imagick') === 0
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;

        return ImageManager::usingDriver($driver);
    }
}
