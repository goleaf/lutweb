<?php

namespace App\Services\Checkout;

final readonly class CheckoutConsentData
{
    public function __construct(
        public string $idempotencyKey,
        public string $ipAddress,
        public ?string $userAgent,
    ) {}
}
