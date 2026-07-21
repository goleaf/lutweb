<?php

namespace App\Services\Checkout;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCheckoutOrder
{
    public function __construct(
        private readonly OrderNumber $orderNumber,
    ) {}

    public function paid(User $user, Product $product, PurchasablePackage $package, CheckoutConsentData $consent): Order
    {
        return $this->create($user, $product, $package, $consent, true);
    }

    public function free(User $user, Product $product, PurchasablePackage $package, CheckoutConsentData $consent): Order
    {
        return $this->create($user, $product, $package, $consent, false);
    }

    private function create(User $user, Product $product, PurchasablePackage $package, CheckoutConsentData $consent, bool $requiresPayment): Order
    {
        return DB::transaction(function () use ($user, $product, $package, $consent, $requiresPayment): Order {
            $existing = Order::query()
                ->with(['item', 'payment'])
                ->where('user_id', $user->id)
                ->where('checkout_idempotency_key', $consent->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Order) {
                return $existing;
            }

            $now = now();
            $status = $requiresPayment ? OrderStatus::Pending : OrderStatus::Completed;
            $paymentStatus = $requiresPayment ? PaymentStatus::Created : PaymentStatus::NotRequired;
            $fulfillmentStatus = $requiresPayment ? FulfillmentStatus::Pending : FulfillmentStatus::Ready;
            $total = $requiresPayment ? $product->price_cents : 0;

            $order = Order::query()->create([
                'number' => $this->orderNumber->make(),
                'user_id' => $user->id,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'fulfillment_status' => $fulfillmentStatus,
                'currency' => 'EUR',
                'subtotal_cents' => $total,
                'tax_cents' => 0,
                'total_cents' => $total,
                'checkout_idempotency_key' => $consent->idempotencyKey,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_country_code' => $user->country_code,
                'terms_of_sale_accepted_at' => $now,
                'license_accepted_at' => $now,
                'digital_delivery_consent_at' => $now,
                'terms_of_sale_version' => config('legal.terms_of_sale_version'),
                'license_version' => config('legal.license_version'),
                'refund_policy_version' => config('legal.refund_policy_version'),
                'digital_delivery_consent_version' => config('legal.digital_delivery_consent_version'),
                'acceptance_ip_address' => $consent->ipAddress,
                'acceptance_user_agent' => Str::limit((string) $consent->userAgent, 500, ''),
                'paid_at' => $requiresPayment ? null : $now,
                'fulfilled_at' => $requiresPayment ? null : $now,
            ]);

            $order->item()->create([
                'product_id' => $product->id,
                'product_version_id' => $package->version->id,
                'product_file_id' => $package->file->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'product_type' => $product->type->value,
                'product_sku' => $product->sku,
                'product_version' => $package->version->version,
                'unit_price_cents' => $total,
                'quantity' => 1,
                'total_cents' => $total,
            ]);

            if ($requiresPayment) {
                $order->payment()->create([
                    'provider' => PaymentProvider::PayPal,
                    'status' => PaymentStatus::Created,
                    'amount_cents' => $total,
                    'currency' => 'EUR',
                    'create_request_id' => (string) Str::uuid(),
                    'refunded_amount_cents' => 0,
                ]);
            }

            return $order->load(['item', 'payment']);
        });
    }
}
