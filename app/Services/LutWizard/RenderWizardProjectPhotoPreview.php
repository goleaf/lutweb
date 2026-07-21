<?php

namespace App\Services\LutWizard;

use App\Models\WizardProjectPhoto;
use App\Services\LutTester\ApplyPreviewWatermark;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;

class RenderWizardProjectPhotoPreview
{
    public function __construct(
        private readonly ApplyPreviewWatermark $watermark,
    ) {}

    /**
     * @return array{path: string, width: int, height: int}
     */
    public function render(WizardProjectPhoto $photo): array
    {
        if ($photo->raw_path === null) {
            throw new RuntimeException('Raw upload is missing.');
        }

        $disk = Storage::disk($photo->disk);

        if (! $disk->exists($photo->raw_path)) {
            throw new RuntimeException('Raw upload is not available.');
        }

        $workDirectory = $this->workDirectory($photo);
        $normalizedPath = $workDirectory.'/source.png';

        try {
            File::ensureDirectoryExists($workDirectory);

            $image = $this->imageManager()->decodePath($disk->path($photo->raw_path));

            if ($image->isAnimated() || $image->count() !== 1) {
                throw new RuntimeException('Animated images are not supported.');
            }

            $image->orient();
            $image->fillTransparentAreas((string) config('lut-wizard.upload.transparency_background', '#ffffff'));

            try {
                $image->setColorspace('rgb');
            } catch (\Throwable) {
                // The GD driver cannot always perform colorspace conversion; decoding still gives a safe RGB preview source.
            }

            $maxEdge = (int) config('lut-wizard.upload.preview_max_edge', 1_920);
            $image->scaleDown($maxEdge, $maxEdge);
            $image->removeProfile();
            $image->encode(new PngEncoder)->save($normalizedPath);

            $previewPath = $this->previewPath($photo);
            $rendered = $this->watermark->apply($normalizedPath, $disk->path($previewPath));

            return [
                'path' => $previewPath,
                'width' => $rendered->width,
                'height' => $rendered->height,
            ];
        } finally {
            File::deleteDirectory($workDirectory, false);
        }
    }

    private function previewPath(WizardProjectPhoto $photo): string
    {
        $project = $photo->wizardProject;

        if ($project === null) {
            throw new RuntimeException('Photo project is missing.');
        }

        return trim((string) config('lut-wizard.prefix', 'custom-lut-projects'), '/')
            .'/'.$project->user_id.'/'.$project->id.'/photos/'.$photo->id.'/preview.webp';
    }

    private function workDirectory(WizardProjectPhoto $photo): string
    {
        return storage_path('app/private/'.trim((string) config('lut-wizard.work_prefix', 'custom-lut-work'), '/').'/'.$photo->id);
    }

    private function imageManager(): ImageManagerInterface
    {
        $driver = strcasecmp((string) config('lut-wizard.upload.image_driver', 'gd'), 'imagick') === 0
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;

        return ImageManager::usingDriver($driver);
    }
}
