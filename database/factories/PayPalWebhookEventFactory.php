<?php

namespace Database\Factories;

use App\Enums\PayPalWebhookProcessingStatus;
use App\Enums\PayPalWebhookVerificationStatus;
use App\Models\PayPalWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PayPalWebhookEvent>
 */
class PayPalWebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = json_encode([
            'id' => 'WH-'.Str::upper(Str::random(12)),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        ], JSON_THROW_ON_ERROR);

        return [
            'paypal_event_id' => 'WH-'.Str::upper(Str::random(12)),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource_type' => 'capture',
            'transmission_id' => (string) Str::uuid(),
            'transmission_time' => now(),
            'verification_status' => PayPalWebhookVerificationStatus::Verified,
            'processing_status' => PayPalWebhookProcessingStatus::Pending,
            'payload_sha256' => hash('sha256', $payload),
            'encrypted_payload' => $payload,
            'processing_attempts' => 0,
            'failure_code' => null,
            'processed_at' => null,
            'payload_purged_at' => null,
        ];
    }
}
