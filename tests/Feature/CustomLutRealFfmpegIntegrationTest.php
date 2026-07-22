<?php

use App\Color\CubeSize;
use App\Services\CustomLutBuilds\PackageNameGenerator;
use App\Services\CustomLutBuilds\WriteCubeFile;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

test('real FFmpeg applies generated Transform V1 CUBE files when explicitly enabled', function () {
    if ((bool) env('RUN_REAL_FFMPEG_TESTS', false) !== true) {
        $this->markTestSkipped('Set RUN_REAL_FFMPEG_TESTS=true to run the real FFmpeg Custom LUT integration test.');
    }

    $ffmpeg = (string) config('custom-lut-builds.ffmpeg_binary', 'ffmpeg');
    $version = Process::timeout(5)->run([$ffmpeg, '-version']);
    $filters = Process::timeout(10)->run([$ffmpeg, '-hide_banner', '-filters']);

    if ($version->failed() || $filters->failed() || ! str_contains($filters->output(), 'lut3d')) {
        $this->markTestSkipped('The configured FFmpeg binary or lut3d filter is unavailable.');
    }

    if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecreatefrompng')) {
        $this->markTestSkipped('GD PNG support is required for the real FFmpeg Custom LUT integration test.');
    }

    $workDir = storage_path('framework/testing/real-ffmpeg-custom-lut-'.Str::random(8));
    File::ensureDirectoryExists($workDir);

    try {
        writeRealFfmpegRgbTestImage($workDir.'/input.png');
        $packageName = app(PackageNameGenerator::class)->make('Real FFmpeg Identity', '01K0REALFFMPEG000000000');
        $neutral = LutTransformParameters::neutral();
        app(WriteCubeFile::class)->handle($workDir.'/neutral.cube', new CubeSize(33), $packageName, $neutral, $neutral->hash());
        runRealFfmpegCube($ffmpeg, $workDir, 'neutral.cube', 'neutral-output.png');
        assertRealFfmpegOutputMatchesInput($workDir.'/input.png', $workDir.'/neutral-output.png', 2);

        $intensityZero = LutTransformParameters::neutral()->withChanges([
            'intensity' => 0,
            'exposure' => 200,
            'contrast' => 1000,
            'temperature' => -1000,
            'saturation' => -1000,
        ]);
        app(WriteCubeFile::class)->handle($workDir.'/intensity-zero.cube', new CubeSize(33), $packageName, $intensityZero, $intensityZero->hash());
        runRealFfmpegCube($ffmpeg, $workDir, 'intensity-zero.cube', 'intensity-zero-output.png');
        assertRealFfmpegOutputMatchesInput($workDir.'/input.png', $workDir.'/intensity-zero-output.png', 2);
    } finally {
        if (is_dir($workDir) && ! is_link($workDir)) {
            File::deleteDirectory($workDir);
        }
    }
});

function writeRealFfmpegRgbTestImage(string $path): void
{
    $image = imagecreatetruecolor(4, 1);

    if ($image === false) {
        throw new RuntimeException('Unable to create real FFmpeg test image.');
    }

    writeRealFfmpegPixel($image, 0, 0, 0, 0, 0);
    writeRealFfmpegPixel($image, 1, 0, 255, 0, 0);
    writeRealFfmpegPixel($image, 2, 0, 0, 255, 0);
    writeRealFfmpegPixel($image, 3, 0, 0, 0, 255);

    if (! imagepng($image, $path)) {
        throw new RuntimeException('Unable to write real FFmpeg test image.');
    }
}

function runRealFfmpegCube(string $ffmpeg, string $workDir, string $cubeName, string $outputName): void
{
    $result = Process::path($workDir)
        ->timeout((int) config('custom-lut-builds.ffmpeg_timeout', 45))
        ->run([
            $ffmpeg,
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
            'format=rgb24,lut3d=file='.$cubeName.':interp=tetrahedral,format=rgb24',
            '-frames:v',
            '1',
            $outputName,
        ]);

    expect($result->successful())->toBeTrue();
    expect(is_file($workDir.'/'.$outputName))->toBeTrue();
}

function assertRealFfmpegOutputMatchesInput(string $inputPath, string $outputPath, int $tolerance): void
{
    $input = imagecreatefrompng($inputPath);
    $output = imagecreatefrompng($outputPath);

    if (! $input instanceof GdImage || ! $output instanceof GdImage) {
        throw new RuntimeException('Unable to decode real FFmpeg output image.');
    }

    expect(imagesx($output))->toBe(imagesx($input))
        ->and(imagesy($output))->toBe(imagesy($input));

    for ($x = 0; $x < imagesx($input); $x++) {
        assertRealFfmpegChannelsClose(
            readRealFfmpegPixel($input, $x, 0),
            readRealFfmpegPixel($output, $x, 0),
            $tolerance,
        );
    }
}

function writeRealFfmpegPixel(GdImage $image, int $x, int $y, int $red, int $green, int $blue): void
{
    $color = imagecolorallocate($image, $red, $green, $blue);

    if ($color === false) {
        throw new RuntimeException('Unable to allocate test image color.');
    }

    imagesetpixel($image, $x, $y, $color);
}

/**
 * @return array{0: int, 1: int, 2: int}
 */
function readRealFfmpegPixel(GdImage $image, int $x, int $y): array
{
    $color = imagecolorat($image, $x, $y);

    if ($color === false) {
        throw new RuntimeException('Unable to read test image pixel.');
    }

    return [
        ($color >> 16) & 0xFF,
        ($color >> 8) & 0xFF,
        $color & 0xFF,
    ];
}

/**
 * @param  array{0: int, 1: int, 2: int}  $expected
 * @param  array{0: int, 1: int, 2: int}  $actual
 */
function assertRealFfmpegChannelsClose(array $expected, array $actual, int $tolerance): void
{
    foreach ([0, 1, 2] as $channel) {
        expect(abs($expected[$channel] - $actual[$channel]))->toBeLessThanOrEqual($tolerance);
    }
}
