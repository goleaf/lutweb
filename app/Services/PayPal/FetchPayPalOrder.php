<?php

namespace App\Services\PayPal;

class FetchPayPalOrder
{
    public function __construct(
        private readonly PayPalHttpClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $paypalOrderId): array
    {
        return $this->client->get('/v2/checkout/orders/'.$paypalOrderId);
    }
}
