<?php

namespace App\Services\PayPal;

final readonly class PayPalCaptureValidationResult
{
    /**
     * @param  array<string, mixed>|null  $capture
     */
    public function __construct(
        public bool $valid,
        public string $paypalStatus,
        public ?string $captureId = null,
        public ?array $capture = null,
        public ?string $failureCode = null,
    ) {}
}
