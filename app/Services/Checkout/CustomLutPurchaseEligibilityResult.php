<?php

namespace App\Services\Checkout;

use App\Models\CustomLutBuildFile;
use App\Models\CustomLutCommerceSetting;
use App\Models\Order;

final readonly class CustomLutPurchaseEligibilityResult
{
    public function __construct(
        public string $state,
        public ?string $message = null,
        public ?CustomLutCommerceSetting $settings = null,
        public ?CustomLutBuildFile $packageFile = null,
        public ?Order $order = null,
    ) {}

    public static function eligible(CustomLutCommerceSetting $settings, CustomLutBuildFile $packageFile): self
    {
        return new self('eligible', settings: $settings, packageFile: $packageFile);
    }

    public static function owned(): self
    {
        return new self('owned', 'You already own this LUT package.');
    }

    public static function resume(Order $order): self
    {
        return new self('resume', order: $order);
    }

    public static function stale(string $message = 'This LUT changed and must be prepared again.'): self
    {
        return new self('stale_build', $message);
    }

    public static function unavailable(string $message): self
    {
        return new self('unavailable', $message);
    }

    public function mayCreateOrder(): bool
    {
        return $this->state === 'eligible' && $this->settings !== null && $this->packageFile !== null;
    }
}
