<?php

namespace App\Services\PayPal;

use App\Services\Checkout\CheckoutReadiness;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PayPalHttpClient
{
    public function __construct(
        private readonly CheckoutReadiness $readiness,
        private readonly PayPalAccessTokenProvider $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload, ?string $requestId = null): array
    {
        return $this->request('post', $path, $payload, $requestId);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $path): array
    {
        return $this->request('get', $path);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = [], ?string $requestId = null, bool $retriedAuth = false): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
            'User-Agent' => 'LUT Web PayPal Client',
        ];

        if ($requestId !== null) {
            $headers['PayPal-Request-Id'] = $requestId;
        }

        try {
            $pending = Http::withHeaders($headers)
                ->withToken($this->tokens->token())
                ->connectTimeout((int) config('paypal.connect_timeout', 5))
                ->timeout((int) config('paypal.timeout', 20))
                ->retry(2, 250, fn ($exception, $request): bool => $exception instanceof ConnectionException);

            $response = $method === 'get'
                ? $pending->get($this->readiness->apiUrl().$path)
                : $pending->post($this->readiness->apiUrl().$path, $payload);
        } catch (ConnectionException $exception) {
            throw new PayPalApiException('PayPal is temporarily unavailable.', null, null);
        }

        if ($response->status() === 401 && ! $retriedAuth) {
            $this->tokens->clear();

            return $this->request($method, $path, $payload, $requestId, true);
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new PayPalApiException('PayPal is temporarily unavailable.', $response->status(), $this->debugId($response->json()));
        }

        if (! $response->successful()) {
            throw new PayPalApiException('PayPal rejected the payment request.', $response->status(), $this->debugId($response->json()));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new PayPalApiException('PayPal returned an invalid response.', $response->status(), null);
        }

        return $json;
    }

    private function debugId(mixed $json): ?string
    {
        return is_array($json) && is_string($json['debug_id'] ?? null) ? $json['debug_id'] : null;
    }
}
