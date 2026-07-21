<?php

namespace Database\Factories;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => PaymentProvider::PayPal,
            'status' => PaymentStatus::Created,
            'amount_cents' => 1999,
            'currency' => 'EUR',
            'paypal_order_id' => 'PAYPAL-ORDER-'.Str::upper(Str::random(12)),
            'paypal_capture_id' => null,
            'create_request_id' => (string) Str::uuid(),
            'capture_request_id' => null,
            'payer_id' => null,
            'payer_email' => null,
            'payer_country_code' => null,
            'payee_merchant_id' => null,
            'paypal_fee_cents' => null,
            'net_amount_cents' => null,
            'refunded_amount_cents' => 0,
            'provider_debug_id' => null,
            'failure_code' => null,
            'approved_at' => null,
            'completed_at' => null,
            'reversed_at' => null,
            'refunded_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Completed,
            'paypal_capture_id' => 'CAPTURE-'.Str::upper(Str::random(12)),
            'completed_at' => now(),
        ]);
    }
}
