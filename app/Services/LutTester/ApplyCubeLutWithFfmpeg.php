<?php

namespace App\Services\LutTester;

use App\Models\LutTestUpload;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

class ApplyCubeLutWithFfmpeg
{
    public function __construct(
        private readonly ApplyPreviewWatermark $watermark,
    ) {}

    public function handle(LutTestUpload $upload, ResolvedPreviewLut $lut): void
    {
        if ($upload->normalized_path === null) {
            throw new RuntimeException('The normalized preview source is missing.');
        }

        $disk = Storage::disk($upload->disk);
        $workDir = $this->workDirectory($upload);

        try {
            File::ensureDirectoryExists($workDir);
            $this->copyStorageFile($disk, $upload->normalized_path, $workDir.'/input.png');
            File::copy($lut->absolutePath, $workDir.'/lut.cube');

            $command = $this->command();
            $result = Process::path($workDir)
                ->timeout((int) config('lut-tester.ffmpeg_timeout', 45))
                ->idleTimeout((int) config('lut-tester.process_idle_timeout', 20))
                ->run($command);

            if ($result->failed()) {
                Log::warning('LUT test FFmpeg processing failed.', [
                    'lut_test_upload_id' => $upload->id,
                    'product_id' => $upload->product_id,
                    'product_version_id' => $lut->version->id,
                    'exit_code' => $result->exitCode(),
                ]);

                throw new RuntimeException('FFmpeg processing failed.');
            }

            $gradedPath = $workDir.'/graded.png';

            if (! is_file($gradedPath)) {
                throw new RuntimeException('FFmpeg did not produce an output image.');
            }

            $this->assertMatchingDimensions($workDir.'/input.png', $gradedPath);

            $beforeTmp = $workDir.'/before.tmp.webp';
            $afterTmp = $workDir.'/after.tmp.webp';
            $before = $this->watermark->apply($workDir.'/input.png', $beforeTmp);
            $after = $this->watermark->apply($gradedPath, $afterTmp);

            if ($before->width !== $after->width || $before->height !== $after->height) {
                throw new RuntimeException('Preview dimensions do not match.');
            }

            $beforePath = $this->previewPath($upload, 'before.webp');
            $afterPath = $this->previewPath($upload, 'after.webp');
            $this->putLocalFile($disk, $beforeTmp, $beforePath);
            $this->putLocalFile($disk, $afterTmp, $afterPath);

            $upload->forceFill([
                'before_preview_path' => $beforePath,
                'after_preview_path' => $afterPath,
                'preview_mime_type' => 'image/webp',
                'preview_width' => $before->width,
                'preview_height' => $before->height,
            ])->save();
        } catch (ProcessTimedOutException $exception) {
            Log::warning('LUT test FFmpeg processing timed out.', [
                'lut_test_upload_id' => $upload->id,
                'product_id' => $upload->product_id,
                'product_version_id' => $lut->version->id,
            ]);

            throw new RuntimeException('FFmpeg processing timed out.', previous: $exception);
        } finally {
            $this->deleteWorkDirectory($workDir);
        }
    }

    /**
     * @return array<int, string>
     */
    public function command(): array
    {
        return [
            (string) config('lut-tester.ffmpeg_binary', 'ffmpeg'),
            '-hide_banner',
            '-loglevel',
            'error',
            '-nostdin',
            '-y',
            '-threads',
            '1',
            '-i',
            'input.png',
            '-vf',
            'format=rgb24,lut3d=file=lut.cube:interp='.(string) config('lut-tester.interpolation', 'tetrahedral').',format=rgb24',
            '-frames:v',
            '1',
            'graded.png',
        ];
    }

    private function assertMatchingDimensions(string $inputPath, string $gradedPath): void
    {
        $manager = ImageManager::usingDriver(
            strcasecmp((string) config('lut-tester.image_driver', 'gd'), 'imagick') === 0
                ? Driver::class
                : \Intervention\Image\Drivers\Gd\Driver::class,
        );

        $input = $manager->decodePath($inputPath);
        $graded = $manager->decodePath($gradedPath);

        if ($input->width() !== $graded->width() || $input->height() !== $graded->height()) {
            throw new RuntimeException('FFmpeg output dimensions do not match the normalized source.');
        }
    }

    private function workDirectory(LutTestUpload $upload): string
    {
        return storage_path('app/private/'.trim((string) config('lut-tester.work_prefix', 'lut-tests-work'), '/').'/'.$upload->id.'-'.bin2hex(random_bytes(6)));
    }

    private function previewPath(LutTestUpload $upload, string $filename): string
    {
        return trim((string) config('lut-tester.prefix', 'lut-tests'), '/')
            .'/'.$upload->user_id.'/'.$upload->id.'/'.$filename;
    }

    private function copyStorageFile(FilesystemAdapter $disk, string $sourcePath, string $destinationPath): void
    {
        $readStream = $disk->readStream($sourcePath);

        if ($readStream === null) {
            throw new RuntimeException('Unable to open private source image.');
        }

        $writeStream = fopen($destinationPath, 'wb');

        if ($writeStream === false) {
            fclose($readStream);
            throw new RuntimeException('Unable to create local FFmpeg source image.');
        }

        try {
            stream_copy_to_stream($readStream, $writeStream);
        } finally {
            fclose($readStream);
            fclose($writeStream);
        }
    }

    private function putLocalFile(FilesystemAdapter $disk, string $localPath, string $storagePath): void
    {
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open generated preview.');
        }

        try {
            $disk->put($storagePath, $stream);
        } finally {
            fclose($stream);
        }
    }

    private function deleteWorkDirectory(string $workDir): void
    {
        $root = storage_path('app/private/'.trim((string) config('lut-tester.work_prefix', 'lut-tests-work'), '/'));

        if (! str_starts_with($workDir, $root) || is_link($workDir) || ! is_dir($workDir)) {
            return;
        }

        File::deleteDirectory($workDir);
    }
}
