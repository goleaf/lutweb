<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

test('real FFmpeg can apply an identity 3D CUBE with tetrahedral interpolation', function () {
    if (! filter_var(env('RUN_REAL_FFMPEG_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set RUN_REAL_FFMPEG_TESTS=true to run the real FFmpeg integration test.');
    }

    $binary = (string) config('lut-tester.ffmpeg_binary', 'ffmpeg');
    $version = Process::timeout(5)->run([$binary, '-version']);

    if ($version->failed()) {
        $this->markTestSkipped('The configured FFmpeg binary is not available.');
    }

    $filters = Process::timeout(5)->run([$binary, '-hide_banner', '-filters']);

    if ($filters->failed() || ! str_contains($filters->output(), 'lut3d')) {
        $this->markTestSkipped('The configured FFmpeg binary does not expose the lut3d filter.');
    }

    $workDir = storage_path('app/private/lut-tests-work/real-ffmpeg-'.Str::random(8));
    mkdir($workDir, 0775, true);

    try {
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 120, 160));
        imagepng($image, $workDir.'/input.png');
        imagedestroy($image);

        file_put_contents($workDir.'/lut.cube', <<<'CUBE'
TITLE "Identity"
LUT_3D_SIZE 2
0 0 0
0 0 1
0 1 0
0 1 1
1 0 0
1 0 1
1 1 0
1 1 1
CUBE);

        $result = Process::path($workDir)->timeout(10)->run([
            $binary,
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
            'format=rgb24,lut3d=file=lut.cube:interp=tetrahedral,format=rgb24',
            '-frames:v',
            '1',
            'graded.png',
        ]);

        expect($result->successful())->toBeTrue()
            ->and(is_file($workDir.'/graded.png'))->toBeTrue()
            ->and(getimagesize($workDir.'/graded.png'))->not->toBeFalse();
    } finally {
        if (is_dir($workDir)) {
            File::deleteDirectory($workDir);
        }
    }
});
