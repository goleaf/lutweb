<?php

namespace App\Services\CustomLutBuilds;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ValidateCubeWithFfmpeg
{
    public function handle(string $cubePath, string $workRoot): void
    {
        if (! (bool) config('custom-lut-builds.ffmpeg_validation_enabled', true)) {
            return;
        }

        $workDir = rtrim($workRoot, '/').'/ffmpeg-'.bin2hex(random_bytes(6));

        try {
            File::ensureDirectoryExists($workDir);
            $this->createInputPng($workDir.'/input.png');
            File::copy($cubePath, $workDir.'/lut.cube');

            $result = Process::path($workDir)
                ->timeout((int) config('custom-lut-builds.ffmpeg_timeout', 45))
                ->run($this->command());

            if ($result->failed()) {
                throw new RuntimeException('FFmpeg rejected the generated CUBE file.');
            }

            $output = $workDir.'/output.png';

            if (! is_file($output)) {
                throw new RuntimeException('FFmpeg did not produce a validation output.');
            }

            $dimensions = getimagesize($output);

            if (! is_array($dimensions) || (int) $dimensions[0] !== 2 || (int) $dimensions[1] !== 2) {
                throw new RuntimeException('FFmpeg validation output dimensions are invalid.');
            }
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('FFmpeg validation timed out.', previous: $exception);
        } finally {
            if (is_dir($workDir) && str_starts_with($workDir, rtrim($workRoot, '/').'/') && ! is_link($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function command(): array
    {
        return [
            (string) config('custom-lut-builds.ffmpeg_binary', 'ffmpeg'),
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
            'format=rgb24,lut3d=file=lut.cube:interp='.(string) config('custom-lut-builds.ffmpeg_interpolation', 'tetrahedral').',format=rgb24',
            '-frames:v',
            '1',
            'output.png',
        ];
    }

    private function createInputPng(string $path): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD is required to create the FFmpeg validation image.');
        }

        $image = imagecreatetruecolor(2, 2);

        if ($image === false) {
            throw new RuntimeException('Unable to create FFmpeg validation image.');
        }

        imagesetpixel($image, 0, 0, $this->color($image, 0, 0, 0));
        imagesetpixel($image, 1, 0, $this->color($image, 255, 0, 0));
        imagesetpixel($image, 0, 1, $this->color($image, 0, 255, 0));
        imagesetpixel($image, 1, 1, $this->color($image, 0, 0, 255));

        if (! imagepng($image, $path)) {
            throw new RuntimeException('Unable to write FFmpeg validation image.');
        }
    }

    private function color(\GdImage $image, int $red, int $green, int $blue): int
    {
        if ($red < 0 || $red > 255 || $green < 0 || $green > 255 || $blue < 0 || $blue > 255) {
            throw new RuntimeException('FFmpeg validation image color is outside the supported channel range.');
        }

        $color = imagecolorallocate($image, $red, $green, $blue);

        if ($color === false) {
            throw new RuntimeException('Unable to allocate FFmpeg validation image color.');
        }

        return $color;
    }
}
