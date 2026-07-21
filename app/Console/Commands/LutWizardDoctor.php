<?php

namespace App\Console\Commands;

use App\Enums\LutTransformVersion;
use App\Models\WizardStyle;
use App\Services\LutTester\ApplyPreviewWatermark;
use App\Services\LutWizard\ValidateWizardStyleConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

class LutWizardDoctor extends Command
{
    protected $signature = 'lut-wizard:doctor';

    protected $description = 'Check Custom LUT Wizard backend configuration and capabilities.';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(ApplyPreviewWatermark $watermark, ValidateWizardStyleConfiguration $validator): int
    {
        $this->check('Wizard enabled state', (bool) config('lut-wizard.enabled'));
        $this->check('Transform version', LutTransformVersion::tryFrom((string) config('lut-wizard.transform_version')) instanceof LutTransformVersion);
        $this->check('Preview LUT size is supported', in_array((int) config('lut-wizard.preview_lut_size'), [17, 33, 65], true));
        $this->check('Private disk exists', array_key_exists((string) config('lut-wizard.disk'), config('filesystems.disks', [])));
        $this->check('Private disk is writable', $this->diskIsWritable());
        $this->check('Image driver is available', $this->imageDriverIsAvailable());
        $this->check('JPEG decode', $this->canEncodeAndDecode(new JpegEncoder));
        $this->check('PNG decode', $this->canEncodeAndDecode(new PngEncoder));
        $this->check('WebP decode', $this->canEncodeAndDecode(new WebpEncoder));
        $this->check('WebP encode', $this->canEncodeWebp());
        $this->check('Text watermark capability', $watermark->canRenderText());
        $this->check('Queue connection', config('queue.default') !== null);
        $this->warnWhen('Production queue is sync', 'Production queue is not sync or app is not production', app()->isProduction() && config('queue.default') === 'sync');
        $this->check('Scheduler contains prune command', str_contains((string) file_get_contents(base_path('routes/console.php')), 'lut-wizard:prune'));
        $this->check('At least one active Wizard Style exists', WizardStyle::query()->where('is_active', true)->exists());
        $this->check('Active styles have valid canonical parameter configuration', $this->activeStylesAreValid($validator));
        $this->check('Active styles use supported transform versions', WizardStyle::query()->where('is_active', true)->get()->every(
            fn (WizardStyle $style): bool => $style->supportsTransformVersion(LutTransformVersion::V1),
        ));
        $this->check('Project and photo expirations are positive', (int) config('lut-wizard.project_expiration_days') > 0 && (int) config('lut-wizard.photo_expiration_minutes') > 0);
        $this->check('Maximum photos is not greater than 3', (int) config('lut-wizard.maximum_photos_per_project') <= 3);
        $this->check('Maximum active projects is positive', (int) config('lut-wizard.maximum_active_projects') > 0);
        $this->check('Variation count is exactly 4', (int) config('lut-wizard.variation_count') === 4);
        $this->check('Variation rate limits are positive', (int) config('lut-wizard.variation_per_minute_limit') > 0 && (int) config('lut-wizard.variation_daily_limit') > 0);
        $this->warnWhen('Worker source is missing from asset tree', 'Worker source is present in asset tree', ! is_file(resource_path('js/workers/lut-preview.worker.ts')));

        $this->line('Doctor complete: '.$this->failures.' FAIL, '.$this->warnings.' WARN.');

        return $this->failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $passes): void
    {
        $this->line(($passes ? 'PASS' : 'FAIL').' '.$label);

        if (! $passes) {
            $this->failures++;
        }
    }

    private function warnWhen(string $warningLabel, string $passLabel, bool $warns): void
    {
        if ($warns) {
            $this->line('WARN '.$warningLabel);
            $this->warnings++;

            return;
        }

        $this->line('PASS '.$passLabel);
    }

    private function diskIsWritable(): bool
    {
        try {
            $disk = Storage::disk((string) config('lut-wizard.disk', 'private'));
            $path = trim((string) config('lut-wizard.work_prefix', 'custom-lut-work'), '/').'/doctor.txt';
            $disk->put($path, 'ok');
            $exists = $disk->exists($path);
            $disk->delete($path);

            return $exists;
        } catch (Throwable) {
            return false;
        }
    }

    private function imageDriverIsAvailable(): bool
    {
        try {
            $this->imageManager()->createImage(8, 8)->fill('rgb(20 20 20)');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function canEncodeAndDecode(EncoderInterface $encoder): bool
    {
        $path = storage_path('app/private/custom-lut-work/doctor-image');

        try {
            File::ensureDirectoryExists(dirname($path));
            $this->imageManager()->createImage(12, 12)->fill('rgb(40 90 120)')->encode($encoder)->save($path);
            $decoded = $this->imageManager()->decodePath($path);

            return $decoded->width() === 12 && $decoded->height() === 12;
        } catch (Throwable) {
            return false;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function canEncodeWebp(): bool
    {
        return $this->canEncodeAndDecode(new WebpEncoder);
    }

    private function activeStylesAreValid(ValidateWizardStyleConfiguration $validator): bool
    {
        try {
            return WizardStyle::query()
                ->where('is_active', true)
                ->get()
                ->every(function (WizardStyle $style) use ($validator): bool {
                    $validator->validate(
                        $style->base_parameters,
                        $style->minimum_parameters,
                        $style->maximum_parameters,
                        $style->variation_amounts,
                    );

                    return true;
                });
        } catch (Throwable) {
            return false;
        }
    }

    private function imageManager(): ImageManagerInterface
    {
        $driver = strcasecmp((string) config('lut-wizard.upload.image_driver', 'gd'), 'imagick') === 0
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;

        return ImageManager::usingDriver($driver);
    }
}
