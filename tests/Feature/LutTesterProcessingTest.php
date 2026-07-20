<?php

use App\Enums\LutTestStatus;
use App\Enums\ProductFileKind;
use App\Jobs\ProcessLutTestUpload;
use App\Models\LutTestUpload;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Models\User;
use App\Services\LutTester\ApplyCubeLutWithFfmpeg;
use App\Services\LutTester\DeleteLutTestUpload;
use App\Services\LutTester\InspectCubeFile;
use App\Services\LutTester\NormalizedPhoto;
use App\Services\LutTester\NormalizeUploadedPhoto;
use App\Services\LutTester\ResolvedPreviewLut;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
});

function lutTesterProcessingIdentityCube(): string
{
    return <<<'CUBE'
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
CUBE;
}

function lutTesterProcessingUpload(array $overrides = []): LutTestUpload
{
    $user = User::factory()->verified()->create();
    $product = Product::factory()->singleLut()->published()->testable()->create();
    $version = ProductVersion::factory()->ready()->current()->for($product)->create();
    $cubePath = 'products/luts/'.$product->id.'.cube';
    Storage::disk('private')->put($cubePath, lutTesterProcessingIdentityCube());
    $file = ProductFile::factory()
        ->for($version, 'productVersion')
        ->create([
            'kind' => ProductFileKind::Cube33,
            'disk' => 'private',
            'path' => $cubePath,
            'original_name' => 'preview.cube',
        ]);
    $rawPath = 'lut-tests/'.$user->id.'/raw/input.jpg';
    Storage::disk('private')->put($rawPath, 'raw');

    return LutTestUpload::factory()
        ->for($user)
        ->for($product)
        ->create([
            'product_version_id' => $version->id,
            'product_file_id' => $file->id,
            'status' => LutTestStatus::Queued,
            'raw_path' => $rawPath,
            'expires_at' => now()->addHour(),
            ...$overrides,
        ]);
}

test('the processing job transitions to ready and records completed_at', function () {
    $upload = lutTesterProcessingUpload();

    $normalize = Mockery::mock(NormalizeUploadedPhoto::class);
    $normalize->shouldReceive('handle')->once()->andReturnUsing(function (LutTestUpload $upload): NormalizedPhoto {
        expect($upload->status)->toBe(LutTestStatus::Processing);

        Storage::disk($upload->disk)->delete($upload->raw_path);
        Storage::disk($upload->disk)->put('lut-tests/'.$upload->user_id.'/'.$upload->id.'/source.png', 'source');

        $upload->forceFill([
            'raw_path' => null,
            'normalized_path' => 'lut-tests/'.$upload->user_id.'/'.$upload->id.'/source.png',
            'preview_width' => 640,
            'preview_height' => 640,
        ])->save();

        return new NormalizedPhoto($upload->normalized_path, 640, 640);
    });

    $ffmpeg = Mockery::mock(ApplyCubeLutWithFfmpeg::class);
    $ffmpeg->shouldReceive('handle')->once()->andReturnUsing(function (LutTestUpload $upload): void {
        Storage::disk($upload->disk)->put('lut-tests/'.$upload->user_id.'/'.$upload->id.'/before.webp', 'before');
        Storage::disk($upload->disk)->put('lut-tests/'.$upload->user_id.'/'.$upload->id.'/after.webp', 'after');

        $upload->forceFill([
            'before_preview_path' => 'lut-tests/'.$upload->user_id.'/'.$upload->id.'/before.webp',
            'after_preview_path' => 'lut-tests/'.$upload->user_id.'/'.$upload->id.'/after.webp',
            'preview_mime_type' => 'image/webp',
        ])->save();
    });

    (new ProcessLutTestUpload($upload))->handle($normalize, app(InspectCubeFile::class), $ffmpeg);

    $upload->refresh();

    expect($upload->status)->toBe(LutTestStatus::Ready)
        ->and($upload->completed_at)->not->toBeNull()
        ->and($upload->raw_path)->toBeNull()
        ->and($upload->normalized_path)->not->toBeNull();

    Storage::disk('private')->assertExists($upload->normalized_path);
    Storage::disk('private')->assertExists($upload->before_preview_path);
    Storage::disk('private')->assertExists($upload->after_preview_path);
});

test('processing an already-ready job is idempotent', function () {
    $upload = lutTesterProcessingUpload(['status' => LutTestStatus::Ready]);

    $normalize = Mockery::mock(NormalizeUploadedPhoto::class);
    $normalize->shouldNotReceive('handle');
    $ffmpeg = Mockery::mock(ApplyCubeLutWithFfmpeg::class);
    $ffmpeg->shouldNotReceive('handle');

    (new ProcessLutTestUpload($upload))->handle($normalize, app(InspectCubeFile::class), $ffmpeg);

    expect($upload->refresh()->status)->toBe(LutTestStatus::Ready);
});

test('a final job failure marks the record failed with a generic message and removes partial files', function () {
    $upload = lutTesterProcessingUpload([
        'normalized_path' => 'lut-tests/1/test/source.png',
        'before_preview_path' => 'lut-tests/1/test/before.webp',
        'after_preview_path' => 'lut-tests/1/test/after.webp',
    ]);
    Storage::disk('private')->put($upload->normalized_path, 'source');
    Storage::disk('private')->put($upload->before_preview_path, 'before');
    Storage::disk('private')->put($upload->after_preview_path, 'after');

    (new ProcessLutTestUpload($upload))->failed(new RuntimeException('ffmpeg stderr that must not leak'));

    $upload->refresh();

    expect($upload->status)->toBe(LutTestStatus::Failed)
        ->and($upload->failure_message)->toBe('We could not process this image.');

    Storage::disk('private')->assertMissing('lut-tests/1/test/source.png');
    Storage::disk('private')->assertMissing('lut-tests/1/test/before.webp');
    Storage::disk('private')->assertMissing('lut-tests/1/test/after.webp');
});

test('FFmpeg is invoked with an argument array in a controlled work directory', function () {
    $upload = lutTesterProcessingUpload([
        'normalized_path' => 'lut-tests/1/test/source.png',
        'original_name' => '../customer.jpg',
    ]);
    $png = imagecreatetruecolor(640, 640);
    $localPng = tempnam(sys_get_temp_dir(), 'lut-source-').'.png';
    imagepng($png, $localPng);
    imagedestroy($png);
    Storage::disk('private')->put($upload->normalized_path, file_get_contents($localPng));

    $file = ProductFile::query()->findOrFail($upload->product_file_id);
    $version = ProductVersion::query()->findOrFail($upload->product_version_id);
    $inspection = app(InspectCubeFile::class)->inspect($file);
    $lut = new ResolvedPreviewLut($version, $file, Storage::disk('private')->path($file->path), $inspection);

    Process::fake(fn () => Process::result('', 'failed', 1));

    expect(fn () => app(ApplyCubeLutWithFfmpeg::class)->handle($upload, $lut))
        ->toThrow(RuntimeException::class);

    Process::assertRan(function ($process): bool {
        return is_array($process->command)
            && in_array('-nostdin', $process->command, true)
            && in_array('format=rgb24,lut3d=file=lut.cube:interp=tetrahedral,format=rgb24', $process->command, true)
            && ! str_contains(implode(' ', $process->command), 'customer.jpg')
            && str_starts_with((string) $process->path, storage_path('app/private/lut-tests-work'));
    });
});

test('deleting a test removes all associated files and tolerates missing files', function () {
    $upload = lutTesterProcessingUpload([
        'raw_path' => 'lut-tests/1/delete/raw.jpg',
        'normalized_path' => 'lut-tests/1/delete/source.png',
        'before_preview_path' => 'lut-tests/1/delete/before.webp',
        'after_preview_path' => 'lut-tests/1/delete/after.webp',
    ]);

    foreach ([$upload->raw_path, $upload->normalized_path, $upload->before_preview_path, $upload->after_preview_path] as $path) {
        Storage::disk('private')->put($path, 'x');
    }

    app(DeleteLutTestUpload::class)->delete($upload);

    expect(LutTestUpload::query()->whereKey($upload->id)->exists())->toBeFalse();
    Storage::disk('private')->assertMissing('lut-tests/1/delete/raw.jpg');
    Storage::disk('private')->assertMissing('lut-tests/1/delete/source.png');
    Storage::disk('private')->assertMissing('lut-tests/1/delete/before.webp');
    Storage::disk('private')->assertMissing('lut-tests/1/delete/after.webp');
});

test('deletion refuses to remove a path outside the controlled prefix', function () {
    $upload = lutTesterProcessingUpload(['raw_path' => 'outside/raw.jpg']);

    expect(fn () => app(DeleteLutTestUpload::class)->delete($upload))
        ->toThrow(RuntimeException::class);

    expect(LutTestUpload::query()->whereKey($upload->id)->exists())->toBeTrue();
});

test('prune deletes expired tests and leaves non-expired tests', function () {
    $expired = lutTesterProcessingUpload([
        'expires_at' => now()->subMinute(),
        'raw_path' => 'lut-tests/1/prune/raw.jpg',
    ]);
    $fresh = lutTesterProcessingUpload(['expires_at' => now()->addHour()]);
    Storage::disk('private')->put($expired->raw_path, 'raw');

    $this->artisan('lut-tests:prune')
        ->assertSuccessful();

    expect(LutTestUpload::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(LutTestUpload::query()->whereKey($fresh->id)->exists())->toBeTrue();
    Storage::disk('private')->assertMissing('lut-tests/1/prune/raw.jpg');
});

test('prune dry-run deletes nothing', function () {
    $expired = lutTesterProcessingUpload(['expires_at' => now()->subMinute()]);

    $this->artisan('lut-tests:prune --dry-run')
        ->assertSuccessful();

    expect(LutTestUpload::query()->whereKey($expired->id)->exists())->toBeTrue();
});
