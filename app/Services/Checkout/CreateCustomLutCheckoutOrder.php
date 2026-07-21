<?php

namespace App\Services\Checkout;

use App\Enums\DigitalAssetKind;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\CustomLutCommerceSetting;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCustomLutCheckoutOrder
{
    public function __construct(
        private readonly CustomLutPurchaseEligibility $eligibility,
        private readonly OrderNumber $orderNumber,
    ) {}

    public function handle(User $user, CustomLutBuild $build, CheckoutConsentData $consent): Order
    {
        return DB::transaction(function () use ($user, $build, $consent): Order {
            $existing = Order::query()
                ->with(['item', 'payment'])
                ->where('user_id', $user->id)
                ->where('checkout_idempotency_key', $consent->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Order) {
                return $existing;
            }

            $lockedBuild = CustomLutBuild::query()
                ->with(['wizardProject', 'packageFile'])
                ->whereKey($build->id)
                ->lockForUpdate()
                ->firstOrFail();

            CustomLutCommerceSetting::query()
                ->where('scope', CustomLutCommerceSetting::Scope)
                ->lockForUpdate()
                ->first();

            $result = $this->eligibility->check($lockedBuild, $user);

            if ($result->state === 'resume' && $result->order instanceof Order) {
                return $result->order;
            }

            if (! $result->mayCreateOrder()) {
                throw ValidationException::withMessages([
                    'custom_lut_build' => $result->message ?? 'This LUT package is not available for purchase.',
                ]);
            }

            $settings = $result->settings;
            $packageFile = $result->packageFile;

            if (! $settings instanceof CustomLutCommerceSetting || ! $packageFile instanceof CustomLutBuildFile) {
                throw ValidationException::withMessages([
                    'custom_lut_build' => 'This LUT package is not available for purchase.',
                ]);
            }

            $now = now();
            $priceCents = $settings->price_cents;

            $order = Order::query()->create([
                'number' => $this->orderNumber->make(),
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Created,
                'fulfillment_status' => FulfillmentStatus::Pending,
                'currency' => 'EUR',
                'subtotal_cents' => $priceCents,
                'tax_cents' => 0,
                'total_cents' => $priceCents,
                'checkout_idempotency_key' => $consent->idempotencyKey,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_country_code' => $user->country_code,
                'terms_of_sale_accepted_at' => $now,
                'license_accepted_at' => $now,
                'digital_delivery_consent_at' => $now,
                'terms_of_sale_version' => config('legal.terms_of_sale_version'),
                'license_version' => $lockedBuild->license_version,
                'refund_policy_version' => config('legal.refund_policy_version'),
                'digital_delivery_consent_version' => config('legal.digital_delivery_consent_version'),
                'acceptance_ip_address' => $consent->ipAddress,
                'acceptance_user_agent' => Str::limit((string) $consent->userAgent, 500, ''),
            ]);

            $order->item()->create([
                'digital_asset_kind' => DigitalAssetKind::CustomLutBuild,
                'product_id' => null,
                'product_version_id' => null,
                'product_file_id' => null,
                'wizard_project_id' => $lockedBuild->wizard_project_id,
                'custom_lut_build_id' => $lockedBuild->id,
                'custom_lut_build_file_id' => $packageFile->id,
                'product_name' => $lockedBuild->project_name_snapshot,
                'product_slug' => $lockedBuild->package_stem,
                'product_type' => null,
                'product_sku' => $this->sku($lockedBuild),
                'product_version' => 'Build '.Str::upper(Str::substr($lockedBuild->id, -8)),
                'custom_lut_build_fingerprint' => $lockedBuild->build_fingerprint,
                'custom_lut_parameters_hash' => $lockedBuild->parameters_hash,
                'custom_lut_transform_version' => $lockedBuild->transform_version,
                'custom_lut_generator_version' => $lockedBuild->generator_version,
                'custom_lut_package_schema_version' => $lockedBuild->package_schema_version,
                'custom_lut_package_sha256' => $packageFile->sha256,
                'custom_lut_package_size_bytes' => $packageFile->size_bytes,
                'custom_lut_style_name_snapshot' => $lockedBuild->style_name_snapshot,
                'custom_lut_pricing_version' => $settings->version,
                'unit_price_cents' => $priceCents,
                'quantity' => 1,
                'total_cents' => $priceCents,
            ]);

            $order->payment()->create([
                'provider' => PaymentProvider::PayPal,
                'status' => PaymentStatus::Created,
                'amount_cents' => $priceCents,
                'currency' => 'EUR',
                'create_request_id' => (string) Str::uuid(),
                'refunded_amount_cents' => 0,
            ]);

            $lockedBuild->forceFill([
                'locked_at' => $lockedBuild->locked_at ?? $now,
                'first_ordered_at' => $lockedBuild->first_ordered_at ?? $now,
            ])->save();

            return $order->load(['item', 'payment']);
        }, attempts: 3);
    }

    private function sku(CustomLutBuild $build): string
    {
        return 'CUSTOM-LUT-'.Str::upper(Str::substr($build->id, 0, 10));
    }
}
