<?php

use App\Actions\Commerce\UpdateCustomLutCommerceSettings;
use App\Enums\CustomLutBuildStatus;
use App\Enums\DigitalAssetKind;
use App\Enums\DownloadStatus;
use App\Enums\EntitlementStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\CustomLutCommerceSetting;
use App\Models\DownloadEvent;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WizardProject;
use App\Services\Checkout\CheckoutConsentData;
use App\Services\Checkout\CreateCustomLutCheckoutOrder;
use App\Services\Checkout\CustomLutPurchaseEligibility;
use App\Services\LutWizard\DeleteWizardProject;
use App\Services\Orders\FulfillPaidOrder;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Cache::flush();
    Storage::fake('private');

    config([
        'custom-lut-commerce.enabled' => true,
        'custom-lut-commerce.private_disk' => 'private',
        'custom-lut-commerce.build_prefix' => 'custom-lut-builds',
        'custom-lut-commerce.verify_package_hash_on_fulfillment' => true,
        'custom-lut-commerce.verify_package_hash_on_download' => false,
        'checkout.enabled' => true,
        'checkout.seller_country_code' => 'LT',
        'checkout.tax_ready' => false,
        'checkout.live_payments_allowed' => false,
        'legal.terms_of_sale_version' => 'terms-v1',
        'legal.license_version' => 'license-v1',
        'legal.refund_policy_version' => 'refund-v1',
        'legal.digital_delivery_consent_version' => 'delivery-v1',
        'paypal.enabled' => true,
        'paypal.mode' => 'sandbox',
        'paypal.client_id' => 'sandbox-client-id',
        'paypal.client_secret' => 'sandbox-client-secret',
        'paypal.webhook_id' => 'sandbox-webhook-id',
        'paypal.merchant_id' => 'MERCHANT-123',
    ]);
});

function customLutCommerceEnableSettings(int $priceCents = 1999): CustomLutCommerceSetting
{
    return CustomLutCommerceSetting::query()->updateOrCreate(
        ['scope' => CustomLutCommerceSetting::Scope],
        [
            'is_enabled' => true,
            'price_cents' => $priceCents,
            'currency' => 'EUR',
            'version' => 1,
        ],
    );
}

/**
 * @return array{0: WizardProject, 1: CustomLutBuild, 2: CustomLutBuildFile, 3: string}
 */
function customLutCommerceBuild(User $user, array $buildState = []): array
{
    $project = WizardProject::factory()
        ->for($user)
        ->create([
            'name' => 'Warm Editorial LUT',
            'revision' => 3,
        ]);

    $build = CustomLutBuild::factory()
        ->for($user)
        ->for($project, 'wizardProject')
        ->saleReady()
        ->create([
            'project_name_snapshot' => $project->name,
            'style_name_snapshot' => 'Neutral',
            'project_revision' => $project->revision,
            'parameters_hash' => $project->parameters_hash,
            'license_version' => config('legal.license_version'),
            'expires_at' => now()->addDays(5),
            ...$buildState,
        ]);

    $zipBytes = 'immutable-custom-lut-package-'.$build->id;
    $path = 'custom-lut-builds/'.$build->id.'/package.zip';

    Storage::disk('private')->put($path, $zipBytes);

    $file = CustomLutBuildFile::factory()
        ->packageZip()
        ->for($build, 'customLutBuild')
        ->create([
            'path' => $path,
            'size_bytes' => strlen($zipBytes),
            'sha256' => hash('sha256', $zipBytes),
        ]);

    return [$project->refresh(), $build->refresh(), $file->refresh(), $zipBytes];
}

function customLutCommerceConsent(?string $idempotencyKey = null): CheckoutConsentData
{
    return new CheckoutConsentData(
        idempotencyKey: $idempotencyKey ?? (string) Str::uuid(),
        ipAddress: '127.0.0.1',
        userAgent: 'Pest Custom LUT commerce',
    );
}

test('administrators update decimal EUR pricing as integer cents', function (): void {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->verified()->create();

    CustomLutCommerceSetting::factory()->create();

    expect(fn () => app(UpdateCustomLutCommerceSettings::class)->handle($customer, '19.99', true))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateCustomLutCommerceSettings::class)->handle($admin, '0.00', true))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateCustomLutCommerceSettings::class)->handle($admin, '-1.00', false))
        ->toThrow(ValidationException::class);

    $setting = app(UpdateCustomLutCommerceSettings::class)->handle($admin, '19.99', true);

    expect($setting->is_enabled)->toBeTrue()
        ->and($setting->price_cents)->toBe(1999)
        ->and($setting->currency)->toBe('EUR')
        ->and($setting->version)->toBe(2)
        ->and($setting->updated_by)->toBe($admin->id);
});

test('custom LUT eligibility blocks disabled settings and detects owned or resumable builds', function (): void {
    $user = User::factory()->verified()->create();
    [, $build] = customLutCommerceBuild($user);
    $eligibility = app(CustomLutPurchaseEligibility::class);

    CustomLutCommerceSetting::factory()->create([
        'is_enabled' => false,
        'price_cents' => 1999,
    ]);

    expect($eligibility->check($build, $user)->state)->toBe('unavailable');

    CustomLutCommerceSetting::query()->delete();
    customLutCommerceEnableSettings(2499);

    $order = app(CreateCustomLutCheckoutOrder::class)->handle($user, $build, customLutCommerceConsent());

    expect($eligibility->check($build->refresh(), $user)->state)->toBe('resume');

    Entitlement::query()->create([
        'user_id' => $user->id,
        'digital_asset_kind' => DigitalAssetKind::CustomLutBuild,
        'order_id' => $order->id,
        'order_item_id' => $order->item->id,
        'wizard_project_id' => $build->wizard_project_id,
        'custom_lut_build_id' => $build->id,
        'custom_lut_build_file_id' => $order->item->custom_lut_build_file_id,
        'status' => EntitlementStatus::Active,
        'granted_at' => now(),
    ]);

    expect($eligibility->check($build->refresh(), $user)->state)->toBe('owned');
});

test('custom LUT checkout page exposes only safe item and legal props', function (): void {
    $user = User::factory()->verified()->create();
    [$project, $build] = customLutCommerceBuild($user);
    customLutCommerceEnableSettings(1999);

    $props = $this->actingAs($user)
        ->get(route('custom-lut.checkout.show', [$project, $build]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomLut/Checkout')
            ->where('state', 'eligible')
            ->where('pricing.currency', 'EUR')
            ->where('pricing.subtotal_cents', 1999)
            ->where('legal.terms_url', route('terms-of-sale'))
            ->where('legal.license_url', route('license'))
            ->where('legal.refund_policy_url', route('refund-policy')))
        ->inertiaProps();

    $json = json_encode($props, JSON_THROW_ON_ERROR);

    expect($json)->toContain('sandbox-client-id')
        ->not->toContain('sandbox-client-secret')
        ->not->toContain('sandbox-webhook-id')
        ->not->toContain('MERCHANT-123')
        ->not->toContain('custom-lut-builds/')
        ->not->toContain('package.zip')
        ->not->toContain($build->parameters_hash);
});

test('custom LUT editor exposes safe commerce state for eligible and owned builds', function (): void {
    $user = User::factory()->verified()->create();
    [$project, $build] = customLutCommerceBuild($user);
    customLutCommerceEnableSettings(1999);

    $props = $this->actingAs($user)
        ->get(route('custom-lut.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomLut/Show')
            ->where('build.commerce.state', 'eligible')
            ->where('build.commerce.price_cents', 1999)
            ->where('build.commerce.price', 'EUR 19.99')
            ->where('build.commerce.checkout_url', route('custom-lut.checkout.show', [$project, $build])))
        ->inertiaProps();

    expect(json_encode($props, JSON_THROW_ON_ERROR))
        ->not->toContain('sandbox-client-secret')
        ->not->toContain('sandbox-webhook-id')
        ->not->toContain('MERCHANT-123')
        ->not->toContain('custom-lut-builds/');

    $order = app(CreateCustomLutCheckoutOrder::class)->handle($user, $build, customLutCommerceConsent());

    Entitlement::query()->create([
        'user_id' => $user->id,
        'digital_asset_kind' => DigitalAssetKind::CustomLutBuild,
        'order_id' => $order->id,
        'order_item_id' => $order->item->id,
        'wizard_project_id' => $build->wizard_project_id,
        'custom_lut_build_id' => $build->id,
        'custom_lut_build_file_id' => $order->item->custom_lut_build_file_id,
        'status' => EntitlementStatus::Active,
        'granted_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('custom-lut.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomLut/Show')
            ->where('build.commerce.state', 'owned')
            ->where('build.commerce.download_url', route('account.custom-luts.download', Entitlement::query()->firstOrFail()))
            ->where('build.commerce.purchased_url', route('account.custom-luts.purchased.show', Entitlement::query()->firstOrFail())));
});

test('dashboard reports catalog and custom LUT counts without leaking another account', function (): void {
    $user = User::factory()->verified()->create();
    $otherUser = User::factory()->verified()->create();
    [, $build] = customLutCommerceBuild($user);
    [, $otherBuild] = customLutCommerceBuild($otherUser);
    customLutCommerceEnableSettings(1999);

    Entitlement::factory()->create([
        'user_id' => $user->id,
        'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
        'status' => EntitlementStatus::Active,
    ]);

    $order = app(CreateCustomLutCheckoutOrder::class)->handle($user, $build, customLutCommerceConsent());
    Entitlement::query()->create([
        'user_id' => $user->id,
        'digital_asset_kind' => DigitalAssetKind::CustomLutBuild,
        'order_id' => $order->id,
        'order_item_id' => $order->item->id,
        'wizard_project_id' => $build->wizard_project_id,
        'custom_lut_build_id' => $build->id,
        'custom_lut_build_file_id' => $order->item->custom_lut_build_file_id,
        'status' => EntitlementStatus::Active,
        'granted_at' => now(),
    ]);

    $otherOrder = app(CreateCustomLutCheckoutOrder::class)->handle($otherUser, $otherBuild, customLutCommerceConsent());
    Entitlement::query()->create([
        'user_id' => $otherUser->id,
        'digital_asset_kind' => DigitalAssetKind::CustomLutBuild,
        'order_id' => $otherOrder->id,
        'order_item_id' => $otherOrder->item->id,
        'wizard_project_id' => $otherBuild->wizard_project_id,
        'custom_lut_build_id' => $otherBuild->id,
        'custom_lut_build_file_id' => $otherOrder->item->custom_lut_build_file_id,
        'status' => EntitlementStatus::Active,
        'granted_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('counts.ready_made_luts', 1)
            ->where('counts.purchased_custom_luts', 1)
            ->where('counts.active_custom_lut_drafts', 1));
});

test('custom LUT order creation snapshots the immutable package and is idempotent', function (): void {
    $user = User::factory()->verified()->create();
    [, $build, $file] = customLutCommerceBuild($user);
    customLutCommerceEnableSettings(2499);
    $idempotencyKey = (string) Str::uuid();

    $order = app(CreateCustomLutCheckoutOrder::class)->handle($user, $build, customLutCommerceConsent($idempotencyKey));
    $sameOrder = app(CreateCustomLutCheckoutOrder::class)->handle($user, $build->refresh(), customLutCommerceConsent($idempotencyKey));
    $resumedOrder = app(CreateCustomLutCheckoutOrder::class)->handle($user, $build->refresh(), customLutCommerceConsent());

    $item = $order->item;

    expect(Order::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and($sameOrder->is($order))->toBeTrue()
        ->and($resumedOrder->is($order))->toBeTrue()
        ->and($order->currency)->toBe('EUR')
        ->and($order->total_cents)->toBe(2499)
        ->and($order->license_version)->toBe($build->license_version)
        ->and($item->digital_asset_kind)->toBe(DigitalAssetKind::CustomLutBuild)
        ->and($item->product_id)->toBeNull()
        ->and($item->product_version_id)->toBeNull()
        ->and($item->product_file_id)->toBeNull()
        ->and($item->custom_lut_build_id)->toBe($build->id)
        ->and($item->custom_lut_build_file_id)->toBe($file->id)
        ->and($item->custom_lut_package_sha256)->toBe($file->sha256)
        ->and($item->custom_lut_package_size_bytes)->toBe($file->size_bytes)
        ->and($item->custom_lut_pricing_version)->toBe(1)
        ->and($item->product_sku)->toStartWith('CUSTOM-LUT-')
        ->and($build->refresh()->locked_at)->not->toBeNull()
        ->and($build->first_ordered_at)->not->toBeNull();
});

test('custom LUT PayPal create order reuses the existing integration and request id', function (): void {
    $user = User::factory()->verified()->create();
    [$project, $build] = customLutCommerceBuild($user);
    customLutCommerceEnableSettings(3299);
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

        return Http::response(['id' => 'PAYPAL-CUSTOM-ORDER', 'status' => 'CREATED'], 201);
    });

    $payload = [
        'checkout_idempotency_key' => $idempotencyKey,
        'terms_of_sale_accepted' => true,
        'license_accepted' => true,
        'digital_delivery_consent_accepted' => true,
        'amount_cents' => 1,
        'currency' => 'USD',
        'custom_lut_build_file_id' => 'attacker-choice',
    ];

    $this->actingAs($user)
        ->postJson(route('custom-lut.checkout.paypal.orders.store', [$project, $build]), $payload)
        ->assertOk()
        ->assertJsonPath('paypal_order_id', 'PAYPAL-CUSTOM-ORDER');

    $this->actingAs($user)
        ->postJson(route('custom-lut.checkout.paypal.orders.store', [$project, $build]), $payload)
        ->assertOk()
        ->assertJsonPath('paypal_order_id', 'PAYPAL-CUSTOM-ORDER');

    $purchaseUnit = $paypalPayload['purchase_units'][0];

    expect($createCalls)->toBe(1)
        ->and($paypalRequestId)->toBe(Order::query()->firstOrFail()->payment->create_request_id)
        ->and($paypalPayload['intent'])->toBe('CAPTURE')
        ->and($purchaseUnit['amount']['currency_code'])->toBe('EUR')
        ->and($purchaseUnit['amount']['value'])->toBe('32.99')
        ->and($purchaseUnit['items'][0]['quantity'])->toBe('1')
        ->and($purchaseUnit['items'][0]['category'])->toBe('DIGITAL_GOODS')
        ->and($purchaseUnit['custom_id'])->toBe(Order::query()->firstOrFail()->id)
        ->and($paypalPayload['payment_source']['paypal']['experience_context']['shipping_preference'])->toBe('NO_SHIPPING')
        ->and(json_encode($paypalPayload, JSON_THROW_ON_ERROR))
        ->not->toContain('custom-lut-builds/')
        ->not->toContain('package.zip')
        ->not->toContain('attacker-choice');
});

test('completed payment fulfills one custom LUT entitlement and streams repeat downloads', function (): void {
    Notification::fake();

    $user = User::factory()->verified()->create();
    [$project, $build, $file, $zipBytes] = customLutCommerceBuild($user);
    customLutCommerceEnableSettings(1999);

    $order = app(CreateCustomLutCheckoutOrder::class)->handle($user, $build, customLutCommerceConsent());

    $order->payment->forceFill([
        'status' => PaymentStatus::Completed,
        'amount_cents' => $order->total_cents,
        'currency' => 'EUR',
        'paypal_order_id' => 'PAYPAL-CUSTOM-ORDER',
        'paypal_capture_id' => 'PAYPAL-CAPTURE-1',
        'completed_at' => now(),
    ])->save();

    $fulfilled = app(FulfillPaidOrder::class)->handle($order->refresh());
    app(FulfillPaidOrder::class)->handle($order->refresh());

    $entitlement = Entitlement::query()->firstOrFail();

    expect(Entitlement::query()->count())->toBe(1)
        ->and($fulfilled->status)->toBe(OrderStatus::Completed)
        ->and($fulfilled->payment_status)->toBe(PaymentStatus::Completed)
        ->and($fulfilled->fulfillment_status)->toBe(FulfillmentStatus::Ready)
        ->and($entitlement->digital_asset_kind)->toBe(DigitalAssetKind::CustomLutBuild)
        ->and($entitlement->custom_lut_build_id)->toBe($build->id)
        ->and($entitlement->product_file_id)->toBeNull()
        ->and($build->refresh()->purchased_at)->not->toBeNull();

    $response = $this->actingAs($user)->get(route('account.custom-luts.download', $entitlement));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/zip')
        ->and($response->headers->get('cache-control'))->toContain('no-store')
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($response->headers->get('content-disposition'))->not->toContain('package.zip')
        ->and($response->streamedContent())->toBe($zipBytes);

    expect(DownloadEvent::query()->count())->toBe(1)
        ->and(DownloadEvent::query()->firstOrFail()->status)->toBe(DownloadStatus::Completed);

    $this->actingAs($user)
        ->get(route('account.custom-luts.download', $entitlement))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('account.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account/Orders/Show')
            ->where('order.item.kind', DigitalAssetKind::CustomLutBuild->value)
            ->where('order.download_url', route('account.custom-luts.download', $entitlement)));
});

test('project deletion preserves an order-referenced custom LUT build and package', function (): void {
    $user = User::factory()->verified()->create();
    [$project, $build, $file] = customLutCommerceBuild($user);
    customLutCommerceEnableSettings(1999);

    app(CreateCustomLutCheckoutOrder::class)->handle($user, $build, customLutCommerceConsent());

    app(DeleteWizardProject::class)->delete($project);

    $this->assertDatabaseHas('custom_lut_builds', [
        'id' => $build->id,
        'wizard_project_id' => null,
    ]);

    $this->assertDatabaseHas('order_items', [
        'custom_lut_build_id' => $build->id,
        'digital_asset_kind' => DigitalAssetKind::CustomLutBuild->value,
    ]);

    expect(Storage::disk('private')->exists($file->path))->toBeTrue();
});

test('stale and unsafe custom LUT builds cannot be purchased', function (): void {
    $user = User::factory()->verified()->create();
    customLutCommerceEnableSettings(1999);
    $eligibility = app(CustomLutPurchaseEligibility::class);

    [, $queued] = customLutCommerceBuild($user, [
        'status' => CustomLutBuildStatus::Queued,
        'sale_ready' => false,
    ]);

    [, $draftDocuments] = customLutCommerceBuild($user, [
        'contains_draft_documents' => true,
    ]);

    [, $superseded] = customLutCommerceBuild($user, [
        'status' => CustomLutBuildStatus::Superseded,
        'is_current' => false,
    ]);

    [, $expired] = customLutCommerceBuild($user, [
        'expires_at' => now()->subMinute(),
    ]);

    expect($eligibility->check($queued, $user)->state)->toBe('unavailable')
        ->and($eligibility->check($draftDocuments, $user)->state)->toBe('unavailable')
        ->and($eligibility->check($superseded, $user)->state)->toBe('stale_build')
        ->and($eligibility->check($expired, $user)->state)->toBe('unavailable');
});

test('custom LUT commerce environment placeholders are documented without secrets', function (): void {
    $contents = file_get_contents(base_path('.env.example'));

    expect($contents)->toContain('CUSTOM_LUT_COMMERCE_ENABLED=false')
        ->and($contents)->toContain('CUSTOM_LUT_SUPPORT_EMAIL=')
        ->and($contents)->toContain('CUSTOM_LUT_VERIFY_PACKAGE_HASH_ON_FULFILLMENT=true')
        ->and($contents)->toContain('CUSTOM_LUT_MAX_ACTIVE_UNPAID_ORDERS=5')
        ->and($contents)->not->toContain('CUSTOM_LUT_PRICE=');
});
