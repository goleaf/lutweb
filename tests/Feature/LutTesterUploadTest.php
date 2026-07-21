<?php

use App\Enums\LutTestStatus;
use App\Enums\ProductFileKind;
use App\Jobs\ProcessLutTestUpload;
use App\Models\LutTestUpload;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function lutTesterUploadIdentityCube(): string
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

function lutTesterUploadProduct(): Product
{
    $product = Product::factory()
        ->singleLut()
        ->published()
        ->testable()
        ->create(['slug' => 'upload-test-lut-'.fake()->unique()->numberBetween(1000, 9999)]);
    $version = ProductVersion::factory()->ready()->current()->for($product)->create();
    $path = 'products/luts/'.$product->id.'-cube33.cube';
    Storage::disk('private')->put($path, lutTesterUploadIdentityCube());

    ProductFile::factory()
        ->for($version, 'productVersion')
        ->create([
            'kind' => ProductFileKind::Cube33,
            'disk' => 'private',
            'path' => $path,
            'original_name' => 'preview.cube',
            'mime_type' => 'text/plain',
        ]);

    return $product->refresh();
}

function lutTesterUploadedRaster(string $name = 'photo.jpg', int $width = 640, int $height = 640): UploadedFile
{
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $path = tempnam(sys_get_temp_dir(), 'lut-photo-').'.'.$extension;
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 120, 90, 70));

    match ($extension) {
        'png' => imagepng($image, $path),
        'webp' => imagewebp($image, $path, 82),
        default => imagejpeg($image, $path, 82),
    };

    imagedestroy($image);

    $mime = match ($extension) {
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'image/jpeg',
    };

    return new UploadedFile($path, $name, $mime, null, true);
}

function lutTesterUploadedText(string $name = 'photo.jpg', string $mime = 'text/plain'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'lut-text-');
    file_put_contents($path, 'not an image');

    return new UploadedFile($path, $name, $mime, null, true);
}

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
    RateLimiter::clear('1|127.0.0.1');
});

test('a guest cannot open the tester', function () {
    $product = lutTesterUploadProduct();

    $this->get(route('shop.tester.create', $product->slug))
        ->assertRedirect(route('login'));
});

test('an unverified user cannot open the tester', function () {
    $product = lutTesterUploadProduct();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('shop.tester.create', $product->slug))
        ->assertRedirect(route('verification.notice'));
});

test('a verified user can open the tester for an eligible product', function () {
    $product = lutTesterUploadProduct();
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->get(route('shop.tester.create', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Try')
            ->where('product.slug', $product->slug)
            ->where('test', null));
});

test('valid JPEG and PNG uploads are accepted', function (string $name) {
    Queue::fake();
    Process::fake();
    $product = lutTesterUploadProduct();
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->post(route('shop.tester.store', $product->slug), [
            'photo' => lutTesterUploadedRaster($name),
        ])
        ->assertRedirect();

    $upload = LutTestUpload::query()->firstOrFail();
    $expectedExpiry = now()->addMinutes((int) config('lut-tester.expires_minutes'));

    expect($upload->id)->toBeString()
        ->and($upload->status)->toBe(LutTestStatus::Queued)
        ->and(abs($upload->expires_at->diffInSeconds($expectedExpiry)))->toBeLessThanOrEqual(1)
        ->and($upload->raw_path)->toStartWith('lut-tests/'.$user->id.'/'.$upload->id.'/raw/')
        ->and($upload->raw_path)->not->toContain($name);

    Storage::disk('private')->assertExists($upload->raw_path);
    Queue::assertPushed(ProcessLutTestUpload::class);
    Process::assertNothingRan();
})->with([
    'JPEG' => ['photo.jpg'],
    'PNG' => ['photo.png'],
]);

test('a valid WebP upload is accepted when runtime support exists', function () {
    if (! function_exists('imagewebp') || ! function_exists('imagecreatefromwebp')) {
        $this->markTestSkipped('WebP support is not available in this PHP runtime.');
    }

    Queue::fake();
    $product = lutTesterUploadProduct();
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->post(route('shop.tester.store', $product->slug), [
            'photo' => lutTesterUploadedRaster('photo.webp'),
        ])
        ->assertRedirect();

    expect(LutTestUpload::query()->count())->toBe(1);
});

test('invalid uploads are rejected', function (Closure $fileFactory) {
    Queue::fake();
    $product = lutTesterUploadProduct();
    $user = User::factory()->verified()->create();
    $file = $fileFactory();

    $this->actingAs($user)
        ->from(route('shop.tester.create', $product->slug))
        ->post(route('shop.tester.store', $product->slug), ['photo' => $file])
        ->assertRedirect(route('shop.tester.create', $product->slug))
        ->assertSessionHasErrors('photo');

    expect(LutTestUpload::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'GIF' => [fn () => lutTesterUploadedRaster('photo.gif')],
    'SVG' => [fn () => lutTesterUploadedText('photo.svg', 'image/svg+xml')],
    'renamed text' => [fn () => lutTesterUploadedText('photo.jpg')],
    'under minimum dimensions' => [fn () => lutTesterUploadedRaster('small.jpg', 100, 100)],
]);

test('a file larger than the configured maximum is rejected', function () {
    config(['lut-tester.max_upload_mb' => 1]);
    $product = lutTesterUploadProduct();
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->post(route('shop.tester.store', $product->slug), [
            'photo' => UploadedFile::fake()->image('large.jpg', 640, 640)->size(1500),
        ])
        ->assertSessionHasErrors('photo');
});

test('an original filename containing path traversal characters is never used as a storage path', function () {
    config(['lut-tester.max_upload_mb' => 10]);
    Queue::fake();
    $product = lutTesterUploadProduct();
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->post(route('shop.tester.store', $product->slug), [
            'photo' => lutTesterUploadedRaster('../evil.jpg'),
        ])
        ->assertRedirect();

    $upload = LutTestUpload::query()->firstOrFail();

    expect($upload->original_name)->toBe('evil.jpg')
        ->and($upload->raw_path)->not->toContain('evil')
        ->and($upload->raw_path)->not->toContain('..');
});

test('upload is rejected when the active-test limit is reached', function () {
    $product = lutTesterUploadProduct();
    $user = User::factory()->verified()->create();

    LutTestUpload::factory()->count(3)->for($user)->for($product)->create([
        'status' => LutTestStatus::Queued,
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($user)
        ->post(route('shop.tester.store', $product->slug), [
            'photo' => lutTesterUploadedRaster(),
        ])
        ->assertSessionHasErrors('photo');
});

test('upload rate limiting works', function () {
    config(['lut-tester.max_active_tests_per_user' => 100]);
    Queue::fake();
    $product = lutTesterUploadProduct();
    $user = User::factory()->verified()->create();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->actingAs($user)
            ->post(route('shop.tester.store', $product->slug), [
                'photo' => lutTesterUploadedRaster('photo-'.$attempt.'.jpg'),
            ]);
    }

    $this->actingAs($user)
        ->post(route('shop.tester.store', $product->slug), [
            'photo' => lutTesterUploadedRaster('photo-6.jpg'),
        ])
        ->assertStatus(429);
});
