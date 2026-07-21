<?php

namespace App\Services\Webhooks;

use App\Services\Checkout\CheckoutReadiness;
use App\Services\PayPal\PayPalAccessTokenProvider;
use App\Services\PayPal\PayPalApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class VerifyPayPalWebhookSignature
{
    public function __construct(
        private readonly CheckoutReadiness $readiness,
        private readonly PayPalAccessTokenProvider $tokens,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function verify(string $rawBody, array $headers, bool $retriedAuth = false): bool
    {
        $verificationBody = $this->verificationBody($rawBody, $headers);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'LUT Web PayPal Webhook Verifier',
            ])
                ->withToken($this->tokens->token())
                ->connectTimeout((int) config('paypal.connect_timeout', 5))
                ->timeout((int) config('paypal.timeout', 20))
                ->withBody($verificationBody, 'application/json')
                ->post($this->readiness->apiUrl().'/v1/notifications/verify-webhook-signature');
        } catch (ConnectionException) {
            throw new PayPalApiException('PayPal webhook verification is temporarily unavailable.');
        }

        if ($response->status() === 401 && ! $retriedAuth) {
            $this->tokens->clear();

            return $this->verify($rawBody, $headers, true);
        }

        if (! $response->successful()) {
            if ($response->serverError() || $response->status() === 429) {
                throw new PayPalApiException('PayPal webhook verification is temporarily unavailable.', $response->status(), $this->debugId($response->json()));
            }

            return false;
        }

        $json = $response->json();

        return is_array($json) && ($json['verification_status'] ?? null) === 'SUCCESS';
    }

    /**
     * @param  array<string, string>  $headers
     *
     * @throws JsonException
     */
    private function verificationBody(string $rawBody, array $headers): string
    {
        return '{'
            .'"transmission_id":'.$this->jsonString($headers['transmission_id']).','
            .'"transmission_time":'.$this->jsonString($headers['transmission_time']).','
            .'"cert_url":'.$this->jsonString($headers['cert_url']).','
            .'"auth_algo":'.$this->jsonString($headers['auth_algo']).','
            .'"transmission_sig":'.$this->jsonString($headers['transmission_sig']).','
            .'"webhook_id":'.$this->jsonString((string) config('paypal.webhook_id')).','
            .'"webhook_event":'.$rawBody
            .'}';
    }

    /**
     * @throws JsonException
     */
    private function jsonString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function debugId(mixed $json): ?string
    {
        return is_array($json) && is_string($json['debug_id'] ?? null) ? $json['debug_id'] : null;
    }
}
