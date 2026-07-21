<?php

namespace App\Services\Checkout;

final readonly class PurchaseEligibilityResult
{
    public function __construct(
        public bool $eligible,
        public string $action,
        public ?string $message = null,
        public ?PurchasablePackage $package = null,
    ) {}

    public static function buy(PurchasablePackage $package): self
    {
        return new self(true, 'buy', null, $package);
    }

    public static function claim(PurchasablePackage $package): self
    {
        return new self(true, 'claim', null, $package);
    }

    public static function owned(): self
    {
        return new self(false, 'owned');
    }

    public static function unavailable(string $message): self
    {
        return new self(false, 'unavailable', $message);
    }
}
