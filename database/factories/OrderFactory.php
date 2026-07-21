<?php

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'ORD-'.now()->format('Ymd').'-'.Str::ulid(),
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Created,
            'fulfillment_status' => FulfillmentStatus::Pending,
            'currency' => 'EUR',
            'subtotal_cents' => 1999,
            'tax_cents' => 0,
            'total_cents' => 1999,
            'checkout_idempotency_key' => (string) Str::uuid(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_country_code' => 'US',
            'terms_of_sale_accepted_at' => now(),
            'license_accepted_at' => now(),
            'digital_delivery_consent_at' => now(),
            'terms_of_sale_version' => config('legal.terms_of_sale_version'),
            'license_version' => config('legal.license_version'),
            'refund_policy_version' => config('legal.refund_policy_version'),
            'digital_delivery_consent_version' => config('legal.digital_delivery_consent_version'),
            'acceptance_ip_address' => '127.0.0.1',
            'acceptance_user_agent' => 'Pest',
            'paid_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Completed,
            'fulfillment_status' => FulfillmentStatus::Ready,
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::NotRequired,
            'fulfillment_status' => FulfillmentStatus::Ready,
            'subtotal_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 0,
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);
    }
}
