<?php

use App\Actions\Notifications\DispatchNotificationOnce;
use App\Enums\StorefrontImageVariantRole;
use App\Http\Middleware\EnforceTrustedHosts;
use App\Http\Resources\Storefront\ProductMediaResource;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use App\Notifications\OrderPaymentConfirmed;
use App\Services\StorefrontMedia\GenerateStorefrontImageVariants;
use App\Services\StorefrontMedia\NormalizeStorefrontSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

function operationalReadinessImageBytes(string $format = 'png'): string
{
    $image = imagecreatetruecolor(500, 500);
    $background = imagecolorallocate($image, 32, 80, 72);
    imagefill($image, 0, 0, $background);

    ob_start();

    match ($format) {
        'jpeg' => imagejpeg($image, quality: 90),
        'webp' => imagewebp($image, quality: 90),
        default => imagepng($image),
    };

    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

test('robots closes the site when indexing is disabled', function (): void {
    config(['seo.indexing_enabled' => false]);

    $this->get(route('robots'))
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8')
        ->assertSee("User-agent: *\nDisallow: /", false);
});

test('robots allows launch storefront routes and points to sitemap only when indexing is enabled', function (): void {
    config([
        'seo.indexing_enabled' => true,
        'seo.canonical_url' => 'https://launch.example',
    ]);

    $this->get(route('robots'))
        ->assertOk()
        ->assertSee('Allow: /')
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /account')
        ->assertSee('Disallow: /checkout')
        ->assertSee('Disallow: /custom-lut')
        ->assertSee('Sitemap: https://launch.example/sitemap.xml');
});

test('sitemap includes public launch URLs and excludes non-public catalog records', function (): void {
    Cache::forget('seo:sitemap:index');

    config([
        'seo.canonical_url' => 'https://launch.example',
    ]);

    $category = Category::factory()->create([
        'name' => 'Portrait & Color',
        'slug' => 'portrait-color',
        'is_active' => true,
    ]);

    $published = Product::factory()->published()->create([
        'name' => 'Launch LUT',
        'slug' => 'launch-lut',
    ]);

    $draft = Product::factory()->create([
        'name' => 'Draft LUT',
        'slug' => 'draft-lut',
    ]);

    $published->categories()->attach($category);
    $draft->categories()->attach($category);

    $response = $this->get(route('sitemap.index'))
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('https://launch.example/')
        ->and($xml)->toContain('https://launch.example/shop')
        ->and($xml)->toContain('https://launch.example/shop/launch-lut')
        ->and($xml)->toContain('https://launch.example/luts/portrait-color')
        ->not->toContain('draft-lut');
});

test('security headers and request id are applied without accepting unsafe ids', function (): void {
    $validRequestId = (string) Str::uuid();

    $this->withHeader('X-Request-ID', $validRequestId)
        ->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Request-ID', $validRequestId)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy-Report-Only');

    $replacement = $this->withHeader('X-Request-ID', 'customer@example.test')
        ->get(route('home'))
        ->assertOk()
        ->headers->get('X-Request-ID');

    expect($replacement)->not->toBe('customer@example.test')
        ->and(Str::isUuid((string) $replacement))->toBeTrue();
});

test('trusted hosts reject unknown production hosts and allow configured hosts', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['security.trusted_hosts' => ['launch.example']]);
    $middleware = new EnforceTrustedHosts;

    try {
        $middleware->handle(
            Request::create('/health/live', 'GET', server: ['HTTP_HOST' => 'unknown.example']),
            fn (): Response => response('ok'),
        );

        $this->fail('Unknown host was accepted.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(400);
    }

    $response = $middleware->handle(
        Request::create('/health/live', 'GET', server: ['HTTP_HOST' => 'launch.example']),
        fn (): Response => response('ok'),
    );

    expect($response->getStatusCode())->toBe(200);
});

test('health endpoints return generic operational JSON', function (): void {
    $this->get(route('health.live'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    $this->get(route('health.ready'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

test('notification dispatch action is idempotent', function (): void {
    Notification::fake();

    $user = User::factory()->verified()->create();
    $order = Order::factory()->for($user)->create([
        'customer_email' => $user->email,
    ]);
    $eventKey = 'order:'.$order->id.':payment-confirmed';

    $action = app(DispatchNotificationOnce::class);
    $action->handle($eventKey, $user, new OrderPaymentConfirmed($order), $order);
    $action->handle($eventKey, $user, new OrderPaymentConfirmed($order), $order);

    expect(NotificationDispatch::query()->where('event_key', $eventKey)->count())->toBe(1);
    Notification::assertSentToTimes($user, OrderPaymentConfirmed::class, 1);
});

test('users set admin creates a sensitive audit event without default credentials', function (): void {
    $user = User::factory()->verified()->create([
        'email' => 'launch-admin@example.test',
        'is_admin' => false,
    ]);

    $this->withHeader('X-Request-ID', (string) Str::uuid())
        ->artisan('users:set-admin launch-admin@example.test')
        ->assertSuccessful();

    $event = AuditEvent::query()->where('action', 'user.admin_promoted')->first();

    expect($user->refresh()->is_admin)->toBeTrue()
        ->and($event)->not->toBeNull()
        ->and($event->target_user_id)->toBe($user->id)
        ->and(json_encode($event->metadata, JSON_THROW_ON_ERROR))->not->toContain('password');
});

test('storefront source normalization accepts still raster images and stores masters privately', function (): void {
    if (! function_exists('imagepng')) {
        $this->markTestSkipped('GD PNG support is unavailable.');
    }

    Storage::fake('private');

    Storage::disk('private')->put('storefront-sources/incoming/source.png', operationalReadinessImageBytes());

    $media = ProductMedia::factory()->create([
        'source_disk' => 'private',
        'source_path' => 'storefront-sources/incoming/source.png',
        'source_original_name' => 'source.png',
        'rights_confirmed_at' => now(),
    ]);

    $source = app(NormalizeStorefrontSource::class)->handle($media);

    expect($source->disk)->toBe('private')
        ->and($source->path)->toStartWith('storefront-sources/')
        ->and($media->refresh()->source_sha256)->toHaveLength(64);
});

test('storefront source normalization rejects renamed non-images', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('storefront-sources/incoming/not-an-image.jpg', 'plain text');

    $media = ProductMedia::factory()->create([
        'source_disk' => 'private',
        'source_path' => 'storefront-sources/incoming/not-an-image.jpg',
        'source_original_name' => 'not-an-image.jpg',
        'rights_confirmed_at' => now(),
    ]);

    app(NormalizeStorefrontSource::class)->handle($media);
})->throws(RuntimeException::class, 'Source image type is not supported.');

test('storefront variants use configured widths without upscaling and do not expose private fields', function (): void {
    if (! function_exists('imagejpeg') || ! function_exists('imagewebp')) {
        $this->markTestSkipped('GD JPEG/WebP support is unavailable.');
    }

    Storage::fake('private');
    Storage::fake('public');

    config([
        'storefront-media.public_disk' => 'public',
        'storefront-media.public_prefix' => 'storefront',
        'storefront-media.responsive_widths' => [480, 768, 1200],
    ]);

    Storage::disk('private')->put('source.png', operationalReadinessImageBytes());

    $media = ProductMedia::factory()->create([
        'path' => '',
        'source_disk' => 'private',
        'source_path' => 'source.png',
        'source_original_name' => 'source.png',
        'rights_confirmed_at' => now(),
    ]);

    $variants = app(GenerateStorefrontImageVariants::class)->handle(
        $media,
        StorefrontImageVariantRole::Media,
        Storage::disk('private')->path('source.png'),
    );

    expect($variants)->toHaveCount(4)
        ->and($variants->pluck('width')->unique()->sort()->values()->all())->toBe([480, 500])
        ->and($variants->pluck('path')->every(fn (string $path): bool => preg_match('#^storefront/media/\d+/media/(480|500)-[a-f0-9]{64}\.(jpeg|webp)$#', $path) === 1))->toBeTrue();

    $payload = (new ProductMediaResource($media->refresh()->load('variants')))->toArray(request());
    $json = json_encode($payload, JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('source_path')
        ->not->toContain('source_disk')
        ->not->toContain('processing_fingerprint')
        ->not->toContain('sha256');
});
