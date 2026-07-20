<?php

namespace App\Console\Commands;

use App\Services\LutTester\ApplyCubeLutWithFfmpeg;
use App\Services\LutTester\ApplyPreviewWatermark;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

#[Signature('lut:doctor')]
#[Description('Check local capabilities required for LUT photo testing.')]
class LutDoctor extends Command
{
    /**
     * @var array<int, string>
     */
    private array $failures = [];

    public function handle(ApplyPreviewWatermark $watermark, ApplyCubeLutWithFfmpeg $ffmpeg): int
    {
        $this->check('LUT tester enabled', (bool) config('lut-tester.enabled'), required: false);
        $this->checkPrivateDisk();
        $this->checkWorkDirectory();
        $this->check('Fileinfo extension', extension_loaded('fileinfo'));
        $this->check('Exif extension', extension_loaded('exif'));
        $this->checkImageDriver();
        $this->checkRasterSupport();
        $this->check('Text watermark rendering', $watermark->canRenderText());
        $this->checkFfmpeg($ffmpeg);
        $this->checkUploadLimits();
        $this->checkQueue();
        $this->checkScheduler();

        return $this->failures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function checkPrivateDisk(): void
    {
        $diskName = (string) config('lut-tester.disk', 'private');

        try {
            $disk = Storage::disk($diskName);
            $path = trim((string) config('lut-tester.prefix', 'lut-tests'), '/').'/.doctor';
            $disk->put($path, 'ok');
            $disk->delete($path);
            $this->pass('Configured private disk exists and is writable');
        } catch (\Throwable) {
            $this->failCheck('Configured private disk exists and is writable');
        }
    }

    private function checkWorkDirectory(): void
    {
        $path = storage_path('app/private/'.trim((string) config('lut-tester.work_prefix', 'lut-tests-work'), '/'));

        try {
            File::ensureDirectoryExists($path);
            $this->check('Temporary work directory is writable', is_dir($path) && is_writable($path));
        } catch (\Throwable) {
            $this->failCheck('Temporary work directory is writable');
        }
    }

    private function checkImageDriver(): void
    {
        $driver = strtolower((string) config('lut-tester.image_driver', 'gd'));

        if ($driver === 'imagick') {
            $this->check('Configured Imagick image driver', extension_loaded('imagick'));

            return;
        }

        $this->check('Configured GD image driver', extension_loaded('gd'));

        if (app()->isProduction()) {
            $this->warnLine('Imagick is recommended for production color handling.');
        }
    }

    private function checkRasterSupport(): void
    {
        $driver = strtolower((string) config('lut-tester.image_driver', 'gd'));

        if ($driver === 'imagick' && extension_loaded('imagick')) {
            $imagick = new \Imagick;
            $formats = array_map('strtoupper', $imagick->queryFormats());
            $this->check('JPEG decode support', in_array('JPEG', $formats, true) || in_array('JPG', $formats, true));
            $this->check('PNG decode support', in_array('PNG', $formats, true));
            $this->check('PNG encode support', in_array('PNG', $formats, true));
            $this->check('WebP decode support', in_array('WEBP', $formats, true));
            $this->check('WebP encode support', in_array('WEBP', $formats, true));

            return;
        }

        $this->check('JPEG decode support', function_exists('imagecreatefromjpeg'));
        $this->check('PNG decode support', function_exists('imagecreatefrompng'));
        $this->check('PNG encode support', function_exists('imagepng'));
        $this->check('WebP decode support', function_exists('imagecreatefromwebp'));
        $this->check('WebP encode support', function_exists('imagewebp'));
    }

    private function checkFfmpeg(ApplyCubeLutWithFfmpeg $ffmpeg): void
    {
        $binary = (string) config('lut-tester.ffmpeg_binary', 'ffmpeg');

        try {
            $version = Process::timeout(5)->run([$binary, '-version']);
            $this->check('FFmpeg executable', $version->successful());
            $this->check('FFmpeg version command', $version->successful());

            if ($version->successful()) {
                $this->line('       '.str($version->output())->before("\n")->toString());
            }

            $filters = Process::timeout(5)->run([$binary, '-hide_banner', '-filters']);
            $this->check('FFmpeg lut3d filter', $filters->successful() && str_contains($filters->output(), 'lut3d'));
        } catch (\Throwable) {
            $this->failCheck('FFmpeg executable');
            $this->failCheck('FFmpeg version command');
            $this->failCheck('FFmpeg lut3d filter');
        }

        $command = $ffmpeg->command();
        $this->check('Configured interpolation value', in_array((string) config('lut-tester.interpolation'), ['tetrahedral'], true));
        $this->check('FFmpeg command uses argument array', $command !== [] && in_array('-nostdin', $command, true) && in_array('1', $command, true));
    }

    private function checkUploadLimits(): void
    {
        $maxUploadBytes = (int) config('lut-tester.max_upload_mb', 20) * 1024 * 1024;
        $this->check('PHP upload_max_filesize', $this->iniBytes((string) ini_get('upload_max_filesize')) >= $maxUploadBytes, required: false);
        $this->check('PHP post_max_size', $this->iniBytes((string) ini_get('post_max_size')) >= $maxUploadBytes, required: false);
    }

    private function checkQueue(): void
    {
        $connection = (string) config('queue.default');
        $this->pass('Current queue connection: '.$connection);

        if (app()->isProduction() && $connection === 'sync') {
            $this->warnLine('Production should not use the sync queue for LUT processing.');
        }
    }

    private function checkScheduler(): void
    {
        $consoleRoutes = file_get_contents(base_path('routes/console.php')) ?: '';
        $this->check('Scheduler definition presence', str_contains($consoleRoutes, 'lut-tests:prune'), required: false);
    }

    private function check(string $label, bool $passed, bool $required = true): void
    {
        if ($passed) {
            $this->pass($label);

            return;
        }

        if ($required) {
            $this->failCheck($label);

            return;
        }

        $this->warnLine($label);
    }

    private function pass(string $label): void
    {
        $this->line('<fg=green>PASS</> '.$label);
    }

    private function failCheck(string $label): void
    {
        $this->line('<fg=red>FAIL</> '.$label);
        $this->failures[] = $label;
    }

    private function warnLine(string $label): void
    {
        $this->line('<fg=yellow>WARN</> '.$label);
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
