<?php

namespace App\Services\PayPal;

use App\Models\Payment;

class CapturePayPalOrder
{
    public function __construct(
        private readonly PayPalHttpClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Payment $payment): array
    {
        if ($payment->paypal_order_id === null || $payment->capture_request_id === null) {
            throw new PayPalApiException('Local payment is not ready for capture.');
        }

        return $this->client->post(
            '/v2/checkout/orders/'.$payment->paypal_order_id.'/capture',
            [],
            $payment->capture_request_id,
        );
    }
}
