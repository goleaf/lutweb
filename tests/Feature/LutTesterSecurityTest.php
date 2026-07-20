<?php

use App\Enums\LutTestStatus;
use App\Enums\ProductFileKind;
use App\Models\LutTestUpload;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
});

function lutTesterSecurityIdentityCube(): string
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

function lutTesterSecurityUpload(?User $user = null, ?Product $product = null, array $overrides = []): LutTestUpload
{
    $user ??= User::factory()->verified()->create();
    $product ??= Product::factory()->singleLut()->published()->testable()->create([
        'slug' => 'security-lut-'.fake()->unique()->numberBetween(1000, 9999),
    ]);

    $before = 'lut-tests/'.$user->id.'/test/before.webp';
    $after = 'lut-tests/'.$user->id.'/test/after.webp';
    Storage::disk('private')->put($before, 'before-webp');
    Storage::disk('private')->put($after, 'after-webp');

    return LutTestUpload::factory()
        ->ready()
        ->for($user)
        ->for($product)
        ->create([
            'disk' => 'private',
            'before_preview_path' => $before,
            'after_preview_path' => $after,
            'expires_at' => now()->addHour(),
            ...$overrides,
        ]);
}

function lutTesterSecurityEligibleProduct(): Product
{
    $product = Product::factory()
        ->singleLut()
        ->published()
        ->testable()
        ->create(['slug' => 'eligible-security-lut']);
    $version = ProductVersion::factory()->ready()->current()->for($product)->create();
    $path = 'products/luts/security.cube';
    Storage::disk('private')->put($path, lutTesterSecurityIdentityCube());

    ProductFile::factory()
        ->for($version, 'productVersion')
        ->create([
            'kind' => ProductFileKind::Cube33,
            'disk' => 'private',
            'path' => $path,
            'original_name' => 'security.cube',
        ]);

    return $product->refresh();
}

function lutTesterSignedImageUrl(LutTestUpload $upload, string $variant = 'before', ?DateTimeInterface $expiresAt = null): string
{
    return URL::temporarySignedRoute('lut-tests.images.show', $expiresAt ?? now()->addMinutes(10), [
        'lutTestUpload' => $upload->id,
        'variant' => $variant,
    ]);
}

test('a user cannot view another user test', function () {
    $owner = User::factory()->verified()->create();
    $other = User::factory()->verified()->create();
    $upload = lutTesterSecurityUpload($owner);

    $this->actingAs($other)
        ->get(route('shop.tester.show', [
            'slug' => $upload->product->slug,
            'lutTestUpload' => $upload->id,
        ]))
        ->assertForbidden();
});

test('a user cannot delete another user test and administrators do not bypass public photo authorization', function () {
    $owner = User::factory()->verified()->create();
    $admin = User::factory()->admin()->create();
    $upload = lutTesterSecurityUpload($owner);

    $this->actingAs($admin)
        ->delete(route('shop.tester.destroy', [
            'slug' => $upload->product->slug,
            'lutTestUpload' => $upload->id,
        ]))
        ->assertForbidden();
});

test('a test must belong to the product slug in the route', function () {
    $user = User::factory()->verified()->create();
    $upload = lutTesterSecurityUpload($user);
    $otherProduct = Product::factory()->singleLut()->published()->testable()->create(['slug' => 'other-lut']);

    $this->actingAs($user)
        ->get(route('shop.tester.show', [
            'slug' => $otherProduct->slug,
            'lutTestUpload' => $upload->id,
        ]))
        ->assertNotFound();
});

test('before preview requires authentication and a valid signed URL', function () {
    $upload = lutTesterSecurityUpload();

    $this->get(lutTesterSignedImageUrl($upload))
        ->assertRedirect(route('login'));

    $this->actingAs($upload->user)
        ->get(route('lut-tests.images.show', [$upload, 'before']))
        ->assertForbidden();
});

test('before and after previews are served privately with safe headers', function (string $variant) {
    $upload = lutTesterSecurityUpload();

    $response = $this->actingAs($upload->user)
        ->get(lutTesterSignedImageUrl($upload, $variant))
        ->assertOk();

    $response->assertHeader('Content-Type', 'image/webp');
    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->toContain('no-store')
        ->toContain('max-age=0');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
})->with([
    'before',
    'after',
]);

test('expired signatures and expired tests cannot serve images', function () {
    $upload = lutTesterSecurityUpload();

    $this->actingAs($upload->user)
        ->get(lutTesterSignedImageUrl($upload, 'before', now()->subMinute()))
        ->assertForbidden();

    $expired = lutTesterSecurityUpload($upload->user, $upload->product, [
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($upload->user)
        ->get(lutTesterSignedImageUrl($expired, 'before'))
        ->assertNotFound();
});

test('a signed URL for another user is rejected', function () {
    $owner = User::factory()->verified()->create();
    $other = User::factory()->verified()->create();
    $upload = lutTesterSecurityUpload($owner);

    $this->actingAs($other)
        ->get(lutTesterSignedImageUrl($upload, 'before'))
        ->assertNotFound();
});

test('source raw and arbitrary variants are rejected', function (string $variant) {
    $upload = lutTesterSecurityUpload();

    $this->actingAs($upload->user)
        ->get(lutTesterSignedImageUrl($upload, $variant))
        ->assertNotFound();
})->with([
    'source',
    'raw',
    'anything',
]);

test('tester page props contain no private paths or ProductFile metadata', function () {
    $upload = lutTesterSecurityUpload();

    $response = $this->actingAs($upload->user)
        ->get(route('shop.tester.show', [
            'slug' => $upload->product->slug,
            'lutTestUpload' => $upload->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Try')
            ->where('test.status', LutTestStatus::Ready->value)
            ->has('test.before_url')
            ->has('test.after_url'));

    $props = json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR);

    expect($props)->not->toContain('raw_path')
        ->not->toContain('normalized_path')
        ->not->toContain('before_preview_path')
        ->not->toContain('after_preview_path')
        ->not->toContain('product_file_id')
        ->not->toContain('private-package')
        ->not->toContain('products/luts');
});

test('product detail enables Try on Your Photo only for eligible products and hides CUBE paths', function () {
    $user = User::factory()->verified()->create();
    $product = lutTesterSecurityEligibleProduct();

    $response = $this->actingAs($user)
        ->get(route('shop.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Show')
            ->where('product.can_test_on_photo', true)
            ->where('product.test_url', route('shop.tester.create', $product->slug)));

    expect(json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR))->not->toContain('.cube');
});

test('failed and expired states receive safe public props', function (LutTestStatus $status) {
    $user = User::factory()->verified()->create();
    $product = Product::factory()->singleLut()->published()->testable()->create(['slug' => 'state-lut-'.$status->value]);
    $upload = LutTestUpload::factory()
        ->for($user)
        ->for($product)
        ->create([
            'status' => $status,
            'expires_at' => $status === LutTestStatus::Expired ? now()->subMinute() : now()->addHour(),
            'failure_message' => $status === LutTestStatus::Failed ? 'We could not process this image.' : null,
        ]);

    $this->actingAs($user)
        ->get(route('shop.tester.show', ['slug' => $product->slug, 'lutTestUpload' => $upload->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('test.status', $status->value)
            ->where('test.before_url', null)
            ->where('test.after_url', null));
})->with([
    LutTestStatus::Failed,
    LutTestStatus::Expired,
]);
