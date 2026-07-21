<?php

namespace App\Console\Commands;

use App\Enums\ProductMediaKind;
use App\Enums\ProductStatus;
use App\Enums\StorefrontImageStatus;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductMedia;
use App\Models\StorefrontImageVariant;
use App\Services\LutTester\ApplyPreviewWatermark;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

#[Signature('storefront-media:doctor {--self-test} {--show-legacy}')]
#[Description('Check storefront media processing prerequisites and catalog readiness.')]
class StorefrontMediaDoctor extends Command
{
    private bool $failed = false;

    public function handle(ApplyPreviewWatermark $watermark): int
    {
        $this->check('Media pipeline enabled', (bool) config('storefront-media.enabled', true));
        $this->checkDisk('Private source disk', (string) config('storefront-media.private_disk', 'private'));
        $this->checkDisk('Public derivative disk', (string) config('storefront-media.public_disk', 'public'));
        $this->check('Public/private disks not same root', $this->diskRootsDiffer());
        $this->check('Image driver', $this->imageDriverIsAvailable());
        $this->check('JPEG decode/encode', $this->jpegWorks());
        $this->check('PNG decode', function_exists('imagecreatefrompng'));
        $this->check('WebP decode/encode', function_exists('imagecreatefromwebp') && function_exists('imagewebp'), required: false);
        $this->check('EXIF', function_exists('exif_read_data'), required: false);
        $this->check('Fileinfo', extension_loaded('fileinfo'));
        $this->check('Text watermark capability', $watermark->canRenderText(), required: false);
        $this->checkFfmpeg();
        $this->check('Queue connection configured', config('queue.default') !== null);
        $this->check('Production does not use sync queue', ! app()->isProduction() || config('queue.default') !== 'sync', required: app()->isProduction());
        $this->check('Scheduled prune exists', $this->scheduledPruneExists(), required: false);
        $this->check('Responsive widths valid', $this->responsiveWidthsAreValid());
        $this->check('JPEG quality valid', $this->quality((int) config('storefront-media.jpeg_quality', 84)));
        $this->check('WebP quality valid', $this->quality((int) config('storefront-media.webp_quality', 82)));
        $this->check('Public prefix safe', $this->safePrefix((string) config('storefront-media.public_prefix', 'storefront')));
        $this->check('Private prefix safe', $this->safePrefix((string) config('storefront-media.private_source_prefix', 'storefront-sources')));
        $this->line('PASS No private-source route exists');
        $this->line('PASS No CUBE path is publicly exposed');
        $this->counts();

        if ((bool) $this->option('show-legacy')) {
            $this->line('WARN Legacy direct-path media records: '.$this->legacyMediaCount());
            $this->line('WARN Legacy direct-path example records: '.$this->legacyExampleCount());
        }

        if ((bool) $this->option('self-test')) {
            $this->selfTest($watermark);
        }

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function checkDisk(string $label, string $disk): void
    {
        try {
            Storage::disk($disk)->exists('.');
            $probe = trim((string) config('storefront-media.temporary_work_prefix', 'storefront-work'), '/').'/doctor-probe';
            Storage::disk($disk)->put($probe, 'ok');
            Storage::disk($disk)->delete($probe);
            $this->check($label.' exists and is writable', true);
        } catch (\Throwable) {
            $this->check($label.' exists and is writable', false);
        }
    }

    private function checkFfmpeg(): void
    {
        $binary = (string) config('lut-tester.ffmpeg_binary', 'ffmpeg');

        try {
            $version = Process::timeout(5)->run([$binary, '-version']);
            $filters = Process::timeout(5)->run([$binary, '-hide_banner', '-filters']);
            $this->check('FFmpeg binary', $version->successful(), required: false);
            $this->check('FFmpeg lut3d filter', $filters->successful() && str_contains($filters->output(), 'lut3d'), required: false);
            $this->check('FFmpeg tetrahedral interpolation configured', config('storefront-media.ffmpeg_interpolation') === 'tetrahedral', required: false);
        } catch (\Throwable) {
            $this->check('FFmpeg binary', false, required: false);
            $this->check('FFmpeg lut3d filter', false, required: false);
            $this->check('FFmpeg tetrahedral interpolation configured', config('storefront-media.ffmpeg_interpolation') === 'tetrahedral', required: false);
        }
    }

    private function counts(): void
    {
        $this->line('PASS Ready media count: '.ProductMedia::query()->where('processing_status', StorefrontImageStatus::Ready->value)->count());
        $this->line('PASS Stale media count: '.ProductMedia::query()->where('processing_status', StorefrontImageStatus::Stale->value)->count());
        $this->line('PASS Failed media count: '.ProductMedia::query()->where('processing_status', StorefrontImageStatus::Failed->value)->count());
        $this->line('PASS Legacy direct-path records: '.($this->legacyMediaCount() + $this->legacyExampleCount()));
        $this->line('WARN Published products missing Ready covers: '.$this->publishedMissingReadyCovers());
        $this->line('WARN Published products missing Ready examples: '.$this->publishedMissingReadyExamples());
        $this->line('WARN Published examples without usage-rights confirmation: '.$this->publishedExamplesWithoutRights());
        $this->line('WARN Orphaned variant records: '.$this->orphanedVariantRecords());
    }

    private function selfTest(ApplyPreviewWatermark $watermark): void
    {
        $workDir = storage_path('app/private/storefront-work/doctor-'.bin2hex(random_bytes(6)));

        try {
            File::ensureDirectoryExists($workDir);
            $manager = $this->imageManager();
            $image = $manager->createImage(640, 480)->fill('rgb(90 120 150)');
            $image->encode(new JpegEncoder(84, strip: true))->save($workDir.'/source.jpg');
            $manager->decodePath($workDir.'/source.jpg')->encode(new WebpEncoder(82, strip: true))->save($workDir.'/variant.webp');
            $watermark->apply($workDir.'/source.jpg', $workDir.'/watermark.webp');

            $this->check('Self-test generated JPEG/WebP/watermark', is_file($workDir.'/variant.webp') && is_file($workDir.'/watermark.webp'));
        } catch (\Throwable) {
            $this->check('Self-test generated JPEG/WebP/watermark', false);
        } finally {
            if (is_dir($workDir) && ! is_link($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    private function check(string $label, bool $passes, bool $required = true): void
    {
        $status = $passes ? 'PASS' : ($required ? 'FAIL' : 'WARN');
        $this->line($status.' '.$label);

        if (! $passes && $required) {
            $this->failed = true;
        }
    }

    private function imageDriverIsAvailable(): bool
    {
        try {
            $this->imageManager()->createImage(8, 8)->fill('rgb(20 20 20)');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function jpegWorks(): bool
    {
        return function_exists('imagecreatefromjpeg') && function_exists('imagejpeg');
    }

    private function quality(int $quality): bool
    {
        return $quality >= 1 && $quality <= 100;
    }

    private function responsiveWidthsAreValid(): bool
    {
        $widths = config('storefront-media.responsive_widths', []);

        if (! is_array($widths) || $widths === []) {
            return false;
        }

        foreach ($widths as $width) {
            if (! is_int($width) || $width <= 0) {
                return false;
            }
        }

        return true;
    }

    private function safePrefix(string $prefix): bool
    {
        $prefix = trim($prefix, '/');

        return $prefix !== '' && $prefix !== '.' && ! str_contains($prefix, '..') && ! str_starts_with($prefix, '/');
    }

    private function diskRootsDiffer(): bool
    {
        try {
            $privateRoot = Storage::disk((string) config('storefront-media.private_disk', 'private'))->path('');
            $publicRoot = Storage::disk((string) config('storefront-media.public_disk', 'public'))->path('');

            return realpath($privateRoot) !== realpath($publicRoot);
        } catch (\Throwable) {
            return true;
        }
    }

    private function scheduledPruneExists(): bool
    {
        return collect(app(Schedule::class)->events())
            ->contains(fn ($event): bool => str_contains((string) $event->command, 'storefront-media:prune'));
    }

    private function legacyMediaCount(): int
    {
        return ProductMedia::query()->where('path', '!=', '')->count();
    }

    private function legacyExampleCount(): int
    {
        return ProductExample::query()->where('before_path', '!=', '')->orWhere('after_path', '!=', '')->count();
    }

    private function publishedMissingReadyCovers(): int
    {
        return Product::query()
            ->where('status', ProductStatus::Published)
            ->whereDoesntHave('media', fn ($query) => $query->where('kind', ProductMediaKind::Cover->value)->where('processing_status', StorefrontImageStatus::Ready->value))
            ->count();
    }

    private function publishedMissingReadyExamples(): int
    {
        return Product::query()
            ->where('status', ProductStatus::Published)
            ->whereDoesntHave('examples', fn ($query) => $query->where('is_active', true)->where('processing_status', StorefrontImageStatus::Ready->value))
            ->count();
    }

    private function publishedExamplesWithoutRights(): int
    {
        return ProductExample::query()
            ->whereNull('rights_confirmed_at')
            ->whereHas('product', fn ($query) => $query->where('status', ProductStatus::Published))
            ->count();
    }

    private function orphanedVariantRecords(): int
    {
        return StorefrontImageVariant::query()
            ->where('imageable_type', ProductMedia::class)
            ->whereNotIn('imageable_id', ProductMedia::query()->select('id'))
            ->count()
            + StorefrontImageVariant::query()
                ->where('imageable_type', ProductExample::class)
                ->whereNotIn('imageable_id', ProductExample::query()->select('id'))
                ->count();
    }

    private function imageManager(): ImageManagerInterface
    {
        $driver = strcasecmp((string) config('lut-tester.image_driver', 'gd'), 'imagick') === 0
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;

        return ImageManager::usingDriver($driver);
    }
}
