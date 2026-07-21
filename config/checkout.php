<?php

return [
    'enabled' => (bool) env('CHECKOUT_ENABLED', false),
    'currency' => 'EUR',
    'seller_country_code' => env('CHECKOUT_SELLER_COUNTRY_CODE'),
    'tax_ready' => (bool) env('CHECKOUT_TAX_READY', false),
    'live_payments_allowed' => (bool) env('CHECKOUT_LIVE_PAYMENTS_ALLOWED', false),
    'pending_order_expires_minutes' => (int) env('CHECKOUT_PENDING_ORDER_EXPIRES_MINUTES', 30),
    'throttles' => [
        'checkout_per_minute' => (int) env('CHECKOUT_ATTEMPTS_PER_MINUTE', 10),
        'checkout_per_hour' => (int) env('CHECKOUT_ATTEMPTS_PER_HOUR', 60),
        'capture_per_minute' => (int) env('CHECKOUT_CAPTURE_ATTEMPTS_PER_MINUTE', 10),
        'free_claims_per_minute' => (int) env('CHECKOUT_FREE_CLAIMS_PER_MINUTE', 5),
        'downloads_per_ten_minutes' => (int) env('CHECKOUT_DOWNLOADS_PER_TEN_MINUTES', 10),
    ],
];
