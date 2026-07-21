<?php

namespace App\Services\Checkout;

use Illuminate\Support\Str;

class CheckoutReadiness
{
    /**
     * @return list<string>
     */
    public function freeCheckoutProblems(): array
    {
        $problems = [];

        if (! (bool) config('checkout.enabled')) {
            $problems[] = 'Checkout is not enabled.';
        }

        return [...$problems, ...$this->legalProblems()];
    }

    /**
     * @return list<string>
     */
    public function paidCheckoutProblems(): array
    {
        $problems = $this->freeCheckoutProblems();

        if (! (bool) config('paypal.enabled')) {
            $problems[] = 'PayPal checkout is not enabled.';
        }

        if (blank(config('paypal.client_id'))) {
            $problems[] = 'PayPal client ID is missing.';
        }

        if (blank(config('paypal.client_secret'))) {
            $problems[] = 'PayPal client secret is missing.';
        }

        if ($this->isLiveMode()) {
            if (! (bool) config('checkout.live_payments_allowed')) {
                $problems[] = 'Live payments are not enabled.';
            }

            if (! (bool) config('checkout.tax_ready')) {
                $problems[] = 'Tax handling is not marked ready.';
            }

            if (! $this->validSellerCountry()) {
                $problems[] = 'Seller country is missing or invalid.';
            }

            if (blank(config('paypal.merchant_id'))) {
                $problems[] = 'PayPal merchant ID is missing.';
            }

            if (blank(config('paypal.webhook_id'))) {
                $problems[] = 'PayPal webhook ID is missing.';
            }

            $problems = [...$problems, ...$this->finalLegalProblems()];
        }

        return array_values(array_unique($problems));
    }

    public function paidCheckoutReady(): bool
    {
        return $this->paidCheckoutProblems() === [];
    }

    public function freeCheckoutReady(): bool
    {
        return $this->freeCheckoutProblems() === [];
    }

    public function isLiveMode(): bool
    {
        return config('paypal.mode') === 'live';
    }

    public function apiUrl(): string
    {
        $mode = (string) config('paypal.mode', 'sandbox');

        return (string) config("paypal.api_urls.{$mode}");
    }

    public function sdkUrl(): string
    {
        $mode = (string) config('paypal.mode', 'sandbox');

        return (string) config("paypal.sdk_urls.{$mode}");
    }

    /**
     * @return list<string>
     */
    private function legalProblems(): array
    {
        return array_values(collect([
            'Terms of Sale version' => config('legal.terms_of_sale_version'),
            'License Agreement version' => config('legal.license_version'),
            'Refund Policy version' => config('legal.refund_policy_version'),
            'digital delivery consent version' => config('legal.digital_delivery_consent_version'),
        ])
            ->filter(fn (mixed $version): bool => blank($version))
            ->keys()
            ->map(fn (string $label): string => "{$label} is missing.")
            ->all());
    }

    /**
     * @return list<string>
     */
    private function finalLegalProblems(): array
    {
        return array_values(collect([
            'Terms of Sale version' => config('legal.terms_of_sale_version'),
            'License Agreement version' => config('legal.license_version'),
            'Refund Policy version' => config('legal.refund_policy_version'),
            'digital delivery consent version' => config('legal.digital_delivery_consent_version'),
        ])
            ->filter(fn (mixed $version): bool => is_string($version) && Str::startsWith($version, 'draft-'))
            ->keys()
            ->map(fn (string $label): string => "{$label} is still a draft.")
            ->all());
    }

    private function validSellerCountry(): bool
    {
        $country = config('checkout.seller_country_code');

        return is_string($country) && preg_match('/^[A-Z]{2}$/', $country) === 1;
    }
}
