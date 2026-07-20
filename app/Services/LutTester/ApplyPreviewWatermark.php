<?php

namespace App\Services\LutTester;

use Illuminate\Support\Facades\File;
use Intervention\Image\Alignment;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Geometry\Factories\LineFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Intervention\Image\Typography\FontFactory;

class ApplyPreviewWatermark
{
    public function apply(string $inputPath, string $outputPath): NormalizedPhoto
    {
        $manager = $this->imageManager();
        $image = $manager->decodePath($inputPath);
        $width = $image->width();
        $height = $image->height();
        $overlay = $manager->createImage($width, $height)->fill('rgba(255 255 255 / 0)');

        $this->drawPattern($overlay, $width, $height);
        $this->drawCentralText($overlay, $width, $height);

        $image->insert($overlay, 0, 0, Alignment::TOP_LEFT, 1);
        $image->removeProfile();

        File::ensureDirectoryExists(dirname($outputPath));
        $image->encode(new WebpEncoder((int) config('lut-tester.preview_quality', 82), strip: true))->save($outputPath);

        return new NormalizedPhoto(
            path: $outputPath,
            width: $width,
            height: $height,
        );
    }

    public function canRenderText(): bool
    {
        $path = storage_path('app/private/lut-tests-work/doctor-watermark.webp');

        try {
            File::ensureDirectoryExists(dirname($path));
            $manager = $this->imageManager();
            $image = $manager->createImage(360, 240)->fill('rgb(40 40 40)');
            $image->text('LUT WEB PREVIEW', 180, 120, fn (FontFactory $font): FontFactory => $this->font($font, 360));
            $image->encode(new WebpEncoder(70, strip: true))->save($path);

            return is_file($path) && filesize($path) > 0;
        } catch (\Throwable) {
            return false;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function drawPattern(ImageInterface $overlay, int $width, int $height): void
    {
        $spacing = max(120, (int) config('lut-tester.watermark_spacing', 360));
        $lineWidth = max(1, (int) floor(min($width, $height) / 420));
        $opacity = $this->opacity((float) config('lut-tester.watermark_pattern_opacity', 0.16));

        for ($x = -$height; $x < $width + $height; $x += $spacing) {
            $overlay->drawLine(fn (LineFactory $line): LineFactory => $line
                ->from($x + 3, 3)
                ->to($x + $height + 3, $height + 3)
                ->width($lineWidth)
                ->color('rgba(0 0 0 / '.$opacity.')'));

            $overlay->drawLine(fn (LineFactory $line): LineFactory => $line
                ->from($x, 0)
                ->to($x + $height, $height)
                ->width($lineWidth)
                ->color('rgba(255 255 255 / '.$opacity.')'));
        }
    }

    private function drawCentralText(ImageInterface $overlay, int $width, int $height): void
    {
        $text = trim((string) config('lut-tester.watermark_text', 'LUT WEB PREVIEW')) ?: 'LUT WEB PREVIEW';
        $overlay->text(
            $text,
            (int) floor($width / 2),
            (int) floor($height / 2),
            fn (FontFactory $font): FontFactory => $this->font($font, min($width, $height)),
        );
    }

    private function font(FontFactory $font, int $basis): FontFactory
    {
        $font->color('rgb(255 255 255)')
            ->stroke('rgb(0 0 0)', 2)
            ->size($this->fontSize($basis))
            ->align(Alignment::CENTER, Alignment::CENTER);

        $fontPath = $this->fontPath();

        if ($fontPath !== null) {
            $font->file($fontPath);
        }

        return $font;
    }

    private function fontSize(int $basis): float
    {
        if ($this->fontPath() === null && strcasecmp((string) config('lut-tester.image_driver', 'gd'), 'gd') === 0) {
            return 5;
        }

        return max(18, min(96, (int) floor($basis / 12)));
    }

    private function fontPath(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/local/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ] as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function imageManager(): ImageManagerInterface
    {
        $driver = strcasecmp((string) config('lut-tester.image_driver', 'gd'), 'imagick') === 0
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;

        return ImageManager::usingDriver($driver);
    }

    private function opacity(float $value): string
    {
        return number_format(max(0, min(1, $value)), 2, '.', '');
    }
}
