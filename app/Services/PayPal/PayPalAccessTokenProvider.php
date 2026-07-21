<?php

namespace App\Services\PayPal;

use App\Services\Checkout\CheckoutReadiness;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PayPalAccessTokenProvider
{
    public function __construct(
        private readonly CheckoutReadiness $readiness,
    ) {}

    public function token(bool $retryAfterClearingCache = true): string
    {
        $key = $this->cacheKey();
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return Cache::lock($key.':lock', 10)->block(5, function () use ($key, $retryAfterClearingCache): string {
            $cached = Cache::get($key);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            try {
                $response = Http::asForm()
                    ->acceptJson()
                    ->withBasicAuth((string) config('paypal.client_id'), (string) config('paypal.client_secret'))
                    ->connectTimeout((int) config('paypal.connect_timeout', 5))
                    ->timeout((int) config('paypal.timeout', 20))
                    ->post($this->readiness->apiUrl().'/v1/oauth2/token', [
                        'grant_type' => 'client_credentials',
                    ]);
            } catch (ConnectionException $exception) {
                throw new PayPalApiException('PayPal authentication is temporarily unavailable.', null, null);
            }

            if ($response->status() === 401 && $retryAfterClearingCache) {
                Cache::forget($key);

                return $this->token(false);
            }

            if (! $response->successful()) {
                throw new PayPalApiException('PayPal authentication failed.', $response->status(), $this->debugId($response->json()));
            }

            $json = $response->json();
            $token = is_array($json) ? ($json['access_token'] ?? null) : null;
            $expiresIn = is_array($json) ? (int) ($json['expires_in'] ?? 0) : 0;

            if (! is_string($token) || $token === '' || $expiresIn <= 0) {
                throw new PayPalApiException('PayPal authentication returned an invalid token response.', $response->status(), $this->debugId($json));
            }

            $ttl = max(1, $expiresIn - (int) config('paypal.oauth_cache_safety_margin', 60));
            Cache::put($key, $token, $ttl);

            return $token;
        });
    }

    public function clear(): void
    {
        Cache::forget($this->cacheKey());
    }

    private function cacheKey(): string
    {
        return 'paypal:oauth:'.config('paypal.mode').':'.hash('sha256', (string) config('paypal.client_id'));
    }

    private function debugId(mixed $json): ?string
    {
        return is_array($json) && is_string($json['debug_id'] ?? null) ? $json['debug_id'] : null;
    }
}
