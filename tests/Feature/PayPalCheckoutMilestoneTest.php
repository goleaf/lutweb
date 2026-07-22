<?php

use App\Enums\EntitlementStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Filament\Resources\Entitlements\EntitlementResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PayPalWebhookEvents\PayPalWebhookEventResource;
use App\Jobs\ProcessPayPalWebhook;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PayPalWebhookEvent;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Models\User;
use App\Notifications\LutReadyForDownload;
use App\Notifications\OrderPaymentConfirmed;
use App\Services\Checkout\CheckoutReadiness;
use App\Services\Checkout\ProductPurchaseEligibility;
use App\Services\PayPal\PayPalAccessTokenProvider;
use App\Services\PayPal\ValidatePayPalCapture;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Cache::flush();
    Storage::fake('private');
});

function paypalMilestoneEnableCheckout(array $overrides = []): void
{
    config([
        'checkout.enabled' => true,
        'checkout.seller_country_code' => 'LT',
        'checkout.tax_ready' => false,
        'checkout.live_payments_allowed' => false,
        'legal.terms_of_sale_version' => 'draft-1',
        'legal.license_version' => 'draft-1',
        'legal.refund_policy_version' => 'draft-1',
        'legal.digital_delivery_consent_version' => 'draft-1',
        'paypal.enabled' => true,
        'paypal.mode' => 'sandbox',
        'paypal.client_id' => 'sandbox-client-id',
        'paypal.client_secret' => 'sandbox-client-secret',
        'paypal.webhook_id' => 'sandbox-webhook-id',
        'paypal.merchant_id' => 'MERCHANT-123',
        'paypal.payee_email' => 'goleaf@gmail.com',
        ...$overrides,
    ]);
}

/**
 * @return array{0: Product, 1: ProductVersion, 2: ProductFile}
 */
function paypalMilestoneProduct(array $productState = [], array $versionState = [], array $fileState = []): array
{
    $product = Product::factory()
        ->published()
        ->singleLut()
        ->create([
            'name' => $productState['name'] ?? 'Cinematic Warm',
            'slug' => $productState['slug'] ?? 'cinematic-warm-'.Str::lower(Str::random(6)),
            'price_cents' => $productState['price_cents'] ?? 1999,
            'currency' => $productState['currency'] ?? 'EUR',
            ...$productState,
        ]);

    $version = ProductVersion::factory()
        ->ready()
        ->current()
        ->for($product)
        ->create([
            'version' => $versionState['version'] ?? 'v1',
            ...$versionState,
        ]);

    $path = $fileState['path'] ?? 'products/releases/'.$product->slug.'-'.$version->version.'.zip';
    Storage::disk('private')->put($path, 'zip-bytes');

    $file = ProductFile::factory()
        ->packageZip()
        ->for($version, 'productVersion')
        ->create([
            'path' => $path,
            'kind' => $fileState['kind'] ?? ProductFileKind::PackageZip,
            'disk' => $fileState['disk'] ?? 'private',
            'original_name' => $fileState['original_name'] ?? 'source-name-not-trusted.zip',
            ...$fileState,
        ]);

    return [$product->refresh(), $version->refresh(), $file->refresh()];
}

/**
 * @return array{0: Order, 1: Payment}
 */
function paypalMilestonePaidOrder(User $user, Product $product, ProductVersion $version, ProductFile $file): array
{
    $order = Order::factory()
        ->for($user)
        ->create([
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Created,
            'fulfillment_status' => FulfillmentStatus::Pending,
            'subtotal_cents' => $product->price_cents,
            'total_cents' => $product->price_cents,
            'customer_email' => $user->email,
        ]);

    $order->item()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'product_file_id' => $file->id,
        'product_name' => $product->name,
        'product_slug' => $product->slug,
        'product_type' => $product->type->value,
        'product_sku' => $product->sku,
        'product_version' => $version->version,
        'unit_price_cents' => $product->price_cents,
        'quantity' => 1,
        'total_cents' => $product->price_cents,
    ]);

    $payment = Payment::factory()
        ->for($order)
        ->create([
            'status' => PaymentStatus::Created,
            'amount_cents' => $product->price_cents,
            'currency' => 'EUR',
            'paypal_order_id' => 'PAYPAL-ORDER-123',
        ]);

    return [$order->refresh(), $payment->refresh()];
}

test('published paid and free products resolve the correct purchase action', function () {
    paypalMilestoneEnableCheckout();

    [$paid] = paypalMilestoneProduct(['type' => ProductType::SingleLut, 'price_cents' => 1999]);
    [$bundle] = paypalMilestoneProduct(['type' => ProductType::Bundle, 'price_cents' => 4999, 'slug' => 'bundle-'.Str::lower(Str::random(6))]);
    [$free] = paypalMilestoneProduct(['type' => ProductType::FreeLut, 'price_cents' => 0, 'slug' => 'free-'.Str::lower(Str::random(6))]);

    $eligibility = app(ProductPurchaseEligibility::class);

    expect($eligibility->check($paid)->action)->toBe('buy')
        ->and($eligibility->check($bundle)->action)->toBe('buy')
        ->and($eligibility->check($free)->action)->toBe('claim');
});

test('purchase eligibility fails closed for unavailable and unsafe live states', function () {
    paypalMilestoneEnableCheckout();

    [$draft] = paypalMilestoneProduct([
        'status' => ProductStatus::Draft,
        'published_at' => null,
    ]);

    [$missingPackage] = paypalMilestoneProduct(['slug' => 'missing-package-'.Str::lower(Str::random(6))]);
    $missingPackage->currentVersion->files()->delete();

    [$liveProduct] = paypalMilestoneProduct(['slug' => 'live-checkout-'.Str::lower(Str::random(6))]);
    config([
        'paypal.mode' => 'live',
        'checkout.tax_ready' => false,
        'checkout.live_payments_allowed' => true,
        'legal.terms_of_sale_version' => 'draft-1',
    ]);

    $eligibility = app(ProductPurchaseEligibility::class);

    expect($eligibility->check($draft)->action)->toBe('unavailable')
        ->and($eligibility->check($missingPackage->refresh())->action)->toBe('unavailable')
        ->and($eligibility->check($liveProduct)->action)->toBe('unavailable')
        ->and($eligibility->check($liveProduct)->message)->toBe('PayPal checkout is not available yet.');
});

test('checkout page renders legal links and never exposes PayPal secrets', function () {
    paypalMilestoneEnableCheckout();
    $user = User::factory()->verified()->create();
    [$product] = paypalMilestoneProduct();

    $props = $this->actingAs($user)
        ->get(route('checkout.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Show')
            ->where('purchase.action', 'buy')
            ->where('legal.terms_of_sale_url', route('terms-of-sale'))
            ->where('legal.license_url', route('license'))
            ->where('legal.refund_policy_url', route('refund-policy')))
        ->inertiaProps();

    $json = json_encode($props, JSON_THROW_ON_ERROR);

    expect($json)->toContain('sandbox-client-id')
        ->not->toContain('sandbox-client-secret')
        ->not->toContain('sandbox-webhook-id')
        ->not->toContain('MERCHANT-123')
        ->not->toContain('products/releases');
});

test('server consent is required before a PayPal order can be created', function () {
    paypalMilestoneEnableCheckout();
    $user = User::factory()->verified()->create();
    [$product] = paypalMilestoneProduct();

    $this->actingAs($user)
        ->postJson(route('checkout.paypal.orders.store', $product->slug), [
            'checkout_idempotency_key' => (string) Str::uuid(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'terms_of_sale_accepted',
            'license_accepted',
            'digital_delivery_consent_accepted',
        ]);

    expect(Order::query()->count())->toBe(0);
});

test('PayPal order creation uses server-side price snapshots and is idempotent', function () {
    paypalMilestoneEnableCheckout();
    $user = User::factory()->verified()->create();
    [$product, $version, $file] = paypalMilestoneProduct();
    $idempotencyKey = (string) Str::uuid();
    $createCalls = 0;
    $paypalPayload = null;
    $paypalRequestId = null;

    Http::fake(function (ClientRequest $request) use (&$createCalls, &$paypalPayload, &$paypalRequestId) {
        if (str_ends_with($request->url(), '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'cached-token', 'expires_in' => 3600]);
        }

        $createCalls++;
        $paypalPayload = $request->data();
        $paypalRequestId = $request->header('PayPal-Request-Id')[0] ?? null;

        return Http::response(['id' => 'PAYPAL-ORDER-123', 'status' => 'CREATED'], 201);
    });

    $payload = [
        'checkout_idempotency_key' => $idempotencyKey,
        'terms_of_sale_accepted' => true,
        'license_accepted' => true,
        'digital_delivery_consent_accepted' => true,
        'amount' => 1,
        'currency' => 'USD',
        'product_file_id' => 999999,
    ];

    $first = $this->actingAs($user)
        ->postJson(route('checkout.paypal.orders.store', $product->slug), $payload)
        ->assertOk()
        ->assertJsonPath('paypal_order_id', 'PAYPAL-ORDER-123')
        ->json();

    $second = $this->actingAs($user)
        ->postJson(route('checkout.paypal.orders.store', $product->slug), $payload)
        ->assertOk()
        ->assertJsonPath('paypal_order_id', 'PAYPAL-ORDER-123')
        ->json();

    $order = Order::query()->with(['item', 'payment'])->firstOrFail();

    expect($second['local_order_id'])->toBe($first['local_order_id'])
        ->and(Order::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and($createCalls)->toBe(1)
        ->and($order->item->product_version_id)->toBe($version->id)
        ->and($order->item->product_file_id)->toBe($file->id)
        ->and($order->total_cents)->toBe(1999)
        ->and($paypalRequestId)->toBe($order->payment->create_request_id)
        ->and($paypalPayload['intent'])->toBe('CAPTURE')
        ->and($paypalPayload['purchase_units'])->toHaveCount(1)
        ->and($paypalPayload['purchase_units'][0]['amount']['currency_code'])->toBe('EUR')
        ->and($paypalPayload['purchase_units'][0]['amount']['value'])->toBe('19.99')
        ->and($paypalPayload['purchase_units'][0]['items'][0]['quantity'])->toBe('1')
        ->and($paypalPayload['purchase_units'][0]['items'][0]['category'])->toBe('DIGITAL_GOODS')
        ->and($paypalPayload['purchase_units'][0]['custom_id'])->toBe($order->id)
        ->and($paypalPayload['purchase_units'][0]['invoice_id'])->toBe($order->number)
        ->and($paypalPayload['purchase_units'][0]['payee'])->toBe([
            'email_address' => 'goleaf@gmail.com',
        ])
        ->and($paypalPayload['payment_source']['paypal']['experience_context']['shipping_preference'])->toBe('NO_SHIPPING')
        ->and(json_encode($paypalPayload, JSON_THROW_ON_ERROR))->not->toContain($file->path);
});

test('OAuth tokens use basic auth and are cached without persistence', function () {
    paypalMilestoneEnableCheckout();
    Cache::flush();
    $oauthCalls = 0;

    Http::fake(function (ClientRequest $request) use (&$oauthCalls) {
        $oauthCalls++;

        expect($request->url())->toBe('https://api-m.sandbox.paypal.com/v1/oauth2/token')
            ->and($request->data()['grant_type'])->toBe('client_credentials')
            ->and($request->header('Authorization')[0] ?? '')->toStartWith('Basic ');

        return Http::response(['access_token' => 'secret-token-value', 'expires_in' => 120]);
    });

    $tokens = app(PayPalAccessTokenProvider::class);

    expect($tokens->token())->toBe('secret-token-value')
        ->and($tokens->token())->toBe('secret-token-value')
        ->and($oauthCalls)->toBe(1)
        ->and(DB::table('payments')->where('provider_debug_id', 'secret-token-value')->exists())->toBeFalse();
});

test('Free LUT claim creates a completed entitlement without PayPal', function () {
    paypalMilestoneEnableCheckout(['paypal.enabled' => false]);
    Notification::fake();
    Http::fake();

    $user = User::factory()->verified()->create();
    [$product] = paypalMilestoneProduct([
        'type' => ProductType::FreeLut,
        'price_cents' => 0,
        'slug' => 'free-claim-'.Str::lower(Str::random(6)),
    ]);
    $payload = [
        'checkout_idempotency_key' => (string) Str::uuid(),
        'terms_of_sale_accepted' => true,
        'license_accepted' => true,
        'digital_delivery_consent_accepted' => true,
    ];

    $this->actingAs($user)
        ->post(route('checkout.free.claim', $product->slug), $payload)
        ->assertRedirect(route('account.luts.index'));

    $this->actingAs($user)
        ->post(route('checkout.free.claim', $product->slug), $payload)
        ->assertRedirect(route('account.luts.index'));

    $order = Order::query()->with('entitlement')->firstOrFail();

    expect(Order::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(0)
        ->and(Entitlement::query()->count())->toBe(1)
        ->and($order->status)->toBe(OrderStatus::Completed)
        ->and($order->payment_status)->toBe(PaymentStatus::NotRequired)
        ->and($order->fulfillment_status)->toBe(FulfillmentStatus::Ready);

    Http::assertNothingSent();
    Notification::assertSentTo($user, LutReadyForDownload::class);
});

test('completed PayPal capture fulfills once and sends safe notifications', function () {
    paypalMilestoneEnableCheckout();
    Notification::fake();

    $user = User::factory()->verified()->create();
    [$product, $version, $file] = paypalMilestoneProduct();
    [$order] = paypalMilestonePaidOrder($user, $product, $version, $file);
    $captureRequestIds = [];

    Http::fake(function (ClientRequest $request) use ($order, &$captureRequestIds) {
        if (str_ends_with($request->url(), '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'capture-token', 'expires_in' => 3600]);
        }

        $captureRequestIds[] = $request->header('PayPal-Request-Id')[0] ?? null;

        return Http::response([
            'id' => 'PAYPAL-ORDER-123',
            'status' => 'COMPLETED',
            'payer' => [
                'payer_id' => 'PAYER-123',
                'email_address' => 'payer@example.com',
                'address' => ['country_code' => 'LT'],
            ],
            'purchase_units' => [[
                'custom_id' => $order->id,
                'invoice_id' => $order->number,
                'payee' => [
                    'merchant_id' => 'MERCHANT-123',
                    'email_address' => 'goleaf@gmail.com',
                ],
                'payments' => [
                    'captures' => [[
                        'id' => 'CAPTURE-123',
                        'status' => 'COMPLETED',
                        'amount' => ['currency_code' => 'EUR', 'value' => '19.99'],
                        'payee' => [
                            'merchant_id' => 'MERCHANT-123',
                            'email_address' => 'goleaf@gmail.com',
                        ],
                        'seller_receivable_breakdown' => [
                            'paypal_fee' => ['currency_code' => 'EUR', 'value' => '0.99'],
                            'net_amount' => ['currency_code' => 'EUR', 'value' => '19.00'],
                        ],
                    ]],
                ],
            ]],
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('account.orders.paypal.capture', $order), ['paypal_order_id' => 'PAYPAL-ORDER-123'])
        ->assertOk()
        ->assertJsonPath('payment_status', PaymentStatus::Completed->value)
        ->assertJsonPath('fulfillment_status', FulfillmentStatus::Ready->value);

    $this->actingAs($user)
        ->postJson(route('account.orders.paypal.capture', $order), ['paypal_order_id' => 'PAYPAL-ORDER-123'])
        ->assertOk();

    $payment = $order->payment()->firstOrFail();

    expect(Entitlement::query()->count())->toBe(1)
        ->and($payment->status)->toBe(PaymentStatus::Completed)
        ->and($payment->paypal_capture_id)->toBe('CAPTURE-123')
        ->and($captureRequestIds)->toHaveCount(1)
        ->and($captureRequestIds[0])->toBe($payment->capture_request_id);

    Notification::assertSentTo($user, OrderPaymentConfirmed::class);
    Notification::assertSentTo($user, LutReadyForDownload::class);
});

test('capture validation rejects a different recipient and accepts a case-insensitive match', function () {
    paypalMilestoneEnableCheckout([
        'paypal.mode' => 'live',
        'checkout.tax_ready' => true,
        'checkout.live_payments_allowed' => true,
        'legal.terms_of_sale_version' => 'terms-v1',
        'legal.license_version' => 'license-v1',
        'legal.refund_policy_version' => 'refund-v1',
        'legal.digital_delivery_consent_version' => 'delivery-v1',
    ]);
    $user = User::factory()->verified()->create();
    [$product, $version, $file] = paypalMilestoneProduct();
    [$order, $payment] = paypalMilestonePaidOrder($user, $product, $version, $file);
    $response = [
        'id' => 'PAYPAL-ORDER-123',
        'status' => 'COMPLETED',
        'purchase_units' => [[
            'custom_id' => $order->id,
            'invoice_id' => $order->number,
            'payee' => [
                'merchant_id' => 'MERCHANT-123',
                'email_address' => 'other@example.com',
            ],
            'payments' => [
                'captures' => [[
                    'id' => 'CAPTURE-RECIPIENT',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'EUR', 'value' => '19.99'],
                ]],
            ],
        ]],
    ];
    $validator = app(ValidatePayPalCapture::class);

    $mismatch = $validator->validate($order, $payment, $response);

    expect($mismatch->valid)->toBeFalse()
        ->and($mismatch->failureCode)->toBe('payee_email_mismatch');

    $response['purchase_units'][0]['payee']['email_address'] = 'GOLEAF@GMAIL.COM';
    $matching = $validator->validate($order, $payment, $response);

    expect($matching->valid)->toBeTrue()
        ->and($matching->failureCode)->toBeNull();
});

test('live checkout readiness requires a valid PayPal recipient email', function (mixed $payeeEmail) {
    paypalMilestoneEnableCheckout([
        'paypal.mode' => 'live',
        'checkout.tax_ready' => true,
        'checkout.live_payments_allowed' => true,
        'paypal.payee_email' => $payeeEmail,
        'legal.terms_of_sale_version' => 'terms-v1',
        'legal.license_version' => 'license-v1',
        'legal.refund_policy_version' => 'refund-v1',
        'legal.digital_delivery_consent_version' => 'delivery-v1',
    ]);

    expect(app(CheckoutReadiness::class)->paidCheckoutProblems())
        ->toContain('PayPal payee email is missing or invalid.');
})->with([
    'missing' => [null],
    'invalid' => ['not-an-email'],
]);

test('secure download streams from private storage and records history', function () {
    paypalMilestoneEnableCheckout(['paypal.enabled' => false]);
    $user = User::factory()->verified()->create();
    $slug = 'downloadable-'.Str::lower(Str::random(6));
    [$product] = paypalMilestoneProduct(
        [
            'type' => ProductType::FreeLut,
            'price_cents' => 0,
            'slug' => $slug,
        ],
        [],
        ['path' => 'products/storefront-preview/'.$slug.'/release/package.zip'],
    );

    $this->actingAs($user)->post(route('checkout.free.claim', $product->slug), [
        'checkout_idempotency_key' => (string) Str::uuid(),
        'terms_of_sale_accepted' => true,
        'license_accepted' => true,
        'digital_delivery_consent_accepted' => true,
    ]);

    $entitlement = Entitlement::query()->firstOrFail();

    $response = $this->actingAs($user)
        ->get(route('account.luts.download', $entitlement))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=0')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Disposition'))->toContain($product->slug)
        ->and($response->headers->get('Content-Disposition'))->not->toContain('source-name-not-trusted')
        ->and($response->streamedContent())->toBe('zip-bytes')
        ->and(DB::table('download_events')->where('status', 'completed')->count())->toBe(1);

    $this->actingAs(User::factory()->verified()->create())
        ->get(route('account.luts.download', $entitlement))
        ->assertNotFound();
});

test('secure download rejects a lookalike storefront package prefix', function () {
    paypalMilestoneEnableCheckout(['paypal.enabled' => false]);
    $user = User::factory()->verified()->create();
    $slug = 'unsafe-prefix-'.Str::lower(Str::random(6));
    [$product] = paypalMilestoneProduct(
        [
            'type' => ProductType::FreeLut,
            'price_cents' => 0,
            'slug' => $slug,
        ],
        [],
        ['path' => 'products/storefront-preview-lookalike/'.$slug.'/package.zip'],
    );

    $this->actingAs($user)->post(route('checkout.free.claim', $product->slug), [
        'checkout_idempotency_key' => (string) Str::uuid(),
        'terms_of_sale_accepted' => true,
        'license_accepted' => true,
        'digital_delivery_consent_accepted' => true,
    ]);

    $entitlement = Entitlement::query()->firstOrFail();

    $this->actingAs($user)
        ->get(route('account.luts.download', $entitlement))
        ->assertNotFound();
});

test('account pages are owner-only and do not expose private paths or credentials', function () {
    paypalMilestoneEnableCheckout(['paypal.enabled' => false]);
    $user = User::factory()->verified()->create();
    [$product] = paypalMilestoneProduct([
        'type' => ProductType::FreeLut,
        'price_cents' => 0,
        'slug' => 'account-safe-'.Str::lower(Str::random(6)),
    ]);

    $this->actingAs($user)->post(route('checkout.free.claim', $product->slug), [
        'checkout_idempotency_key' => (string) Str::uuid(),
        'terms_of_sale_accepted' => true,
        'license_accepted' => true,
        'digital_delivery_consent_accepted' => true,
    ]);

    $order = Order::query()->firstOrFail();
    $otherUser = User::factory()->verified()->create();

    $this->actingAs($otherUser)
        ->get(route('account.orders.show', $order))
        ->assertNotFound();

    $props = $this->actingAs($user)
        ->get(route('account.luts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account/Luts/Index')
            ->has('entitlements.data', 1))
        ->inertiaProps();

    $json = json_encode($props, JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('products/releases')
        ->not->toContain('sandbox-client-secret')
        ->not->toContain('MERCHANT-123')
        ->not->toContain('sandbox-webhook-id');
});

test('webhooks require PayPal headers and verified signatures before queueing', function () {
    paypalMilestoneEnableCheckout();
    Queue::fake();

    $this->postJson(route('webhooks.paypal'), ['id' => 'WH-MISSING', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED'])
        ->assertBadRequest();

    $rawPayload = json_encode([
        'id' => 'WH-VERIFIED-1',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource_type' => 'capture',
        'resource' => ['id' => 'CAPTURE-123'],
    ], JSON_THROW_ON_ERROR);
    $verificationBody = null;

    Http::fake(function (ClientRequest $request) use (&$verificationBody) {
        if (str_ends_with($request->url(), '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'webhook-token', 'expires_in' => 3600]);
        }

        $verificationBody = $request->body();

        return Http::response(['verification_status' => 'SUCCESS']);
    });

    $this->call('POST', route('webhooks.paypal'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYPAL_TRANSMISSION_ID' => 'tx-1',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'signature',
        'HTTP_PAYPAL_CERT_URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
        'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
    ], $rawPayload)->assertOk();

    $event = PayPalWebhookEvent::query()->firstOrFail();
    $storedPayload = DB::table('paypal_webhook_events')->value('encrypted_payload');

    expect($verificationBody)->toContain('"webhook_id":"sandbox-webhook-id"')
        ->and($verificationBody)->toContain('"webhook_event":'.$rawPayload)
        ->and($event->payload_sha256)->toBe(hash('sha256', $rawPayload))
        ->and($event->encrypted_payload)->toBe($rawPayload)
        ->and($storedPayload)->not->toBe($rawPayload);

    Queue::assertPushed(ProcessPayPalWebhook::class);

    $this->call('POST', route('webhooks.paypal'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYPAL_TRANSMISSION_ID' => 'tx-1',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'signature',
        'HTTP_PAYPAL_CERT_URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
        'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
    ], $rawPayload)->assertOk();

    expect(PayPalWebhookEvent::query()->count())->toBe(1);
});

test('Filament sales resources are visible to admins and blocked for customers', function (string $url) {
    $admin = User::factory()->admin()->verified()->create();
    $customer = User::factory()->verified()->create();

    $this->actingAs($admin)
        ->get($url)
        ->assertOk();

    $this->actingAs($customer)
        ->get($url)
        ->assertForbidden();
})->with([
    'orders' => fn () => OrderResource::getUrl('index'),
    'payments' => fn () => PaymentResource::getUrl('index'),
    'entitlements' => fn () => EntitlementResource::getUrl('index'),
    'paypal webhooks' => fn () => PayPalWebhookEventResource::getUrl('index'),
]);

test('doctor reports disabled or unsafe live checkout without printing secrets', function () {
    paypalMilestoneEnableCheckout([
        'paypal.enabled' => false,
        'checkout.enabled' => false,
        'paypal.mode' => 'live',
        'checkout.tax_ready' => false,
        'checkout.live_payments_allowed' => false,
        'paypal.client_secret' => 'never-print-this-secret',
    ]);

    $exitCode = Artisan::call('paypal:doctor');
    $output = Artisan::output();

    expect($exitCode)->toBeGreaterThan(0)
        ->and($output)->toContain('FAIL')
        ->and($output)->toContain('PayPal enabled state: false')
        ->and($output)->toContain('PayPal recipient email: configured')
        ->and($output)->not->toContain('goleaf@gmail.com')
        ->and($output)->not->toContain('never-print-this-secret')
        ->and($output)->not->toContain('access_token');

    Artisan::call('paypal:doctor', ['--show-recipient' => true]);

    expect(Artisan::output())->toContain('PayPal recipient email: goleaf@gmail.com');
});

test('sold package ZIPs and purchased versions are immutable', function () {
    paypalMilestoneEnableCheckout(['paypal.enabled' => false]);
    $user = User::factory()->verified()->create();
    [$product, $version, $file] = paypalMilestoneProduct([
        'type' => ProductType::FreeLut,
        'price_cents' => 0,
        'slug' => 'immutable-'.Str::lower(Str::random(6)),
    ]);

    $this->actingAs($user)
        ->post(route('checkout.free.claim', $product->slug), [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'terms_of_sale_accepted' => true,
            'license_accepted' => true,
            'digital_delivery_consent_accepted' => true,
        ])
        ->assertRedirect(route('account.luts.index'));

    expect(OrderItem::query()->where('product_file_id', $file->id)->exists())->toBeTrue();

    expect(fn () => $file->delete())->toThrow(ValidationException::class)
        ->and(fn () => $version->delete())->toThrow(ValidationException::class);

    $entitlement = Entitlement::query()->firstOrFail();
    $product->forceFill(['status' => ProductStatus::Archived])->save();
    $product->delete();

    expect($entitlement->refresh()->status)->toBe(EntitlementStatus::Active);
});

test('non-ready or non-private package files are unavailable', function () {
    paypalMilestoneEnableCheckout();

    [$draftVersionProduct] = paypalMilestoneProduct(
        ['slug' => 'draft-version-'.Str::lower(Str::random(6))],
        ['status' => ProductVersionStatus::Draft],
    );
    [$publicFileProduct, , $publicFile] = paypalMilestoneProduct(
        ['slug' => 'public-file-'.Str::lower(Str::random(6))],
        [],
    );
    ProductFile::withoutEvents(fn () => $publicFile->forceFill(['disk' => 'public'])->save());

    $eligibility = app(ProductPurchaseEligibility::class);

    expect($eligibility->check($draftVersionProduct)->action)->toBe('unavailable')
        ->and($eligibility->check($publicFileProduct)->action)->toBe('unavailable');
});
