<?php

namespace App\Http\Requests\Checkout;

use App\Services\Checkout\CheckoutConsentData;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasVerifiedEmail() && ! $user->is_suspended;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'checkout_idempotency_key' => ['required', 'uuid'],
            'terms_of_sale_accepted' => ['accepted'],
            'license_accepted' => ['accepted'],
            'digital_delivery_consent_accepted' => ['accepted'],
        ];
    }

    public function consentData(): CheckoutConsentData
    {
        return new CheckoutConsentData(
            idempotencyKey: (string) $this->validated('checkout_idempotency_key'),
            ipAddress: (string) $this->ip(),
            userAgent: $this->userAgent(),
        );
    }
}
