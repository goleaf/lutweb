<?php

return [
    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_ALLOWED_HOSTS', ''))))),
    'headers_enabled' => env('SECURITY_HEADERS_ENABLED', true),
    'csp_enabled' => env('CSP_ENABLED', true),
    'csp_report_only' => env('CSP_REPORT_ONLY', true),
    'hsts_enabled' => env('HSTS_ENABLED', false),
    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31_536_000),
    'hsts_include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', false),
    'hsts_preload' => env('HSTS_PRELOAD', false),
    'allowed_public_asset_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_PUBLIC_ASSET_HOSTS', ''))))),
    'paypal_browser_hosts' => [
        'https://www.paypal.com',
        'https://www.sandbox.paypal.com',
        'https://www.paypalobjects.com',
    ],
    'request_id_header' => env('SECURITY_REQUEST_ID_HEADER', 'X-Request-ID'),
    'health_rate_limit' => env('SECURITY_HEALTH_RATE_LIMIT', '60,1'),
    'health' => [
        'rate_limit_per_minute' => (int) env('SECURITY_HEALTH_RATE_LIMIT_PER_MINUTE', 120),
        'cache_seconds' => (int) env('SECURITY_HEALTH_CACHE_SECONDS', 10),
        'queue_heartbeat_stale_seconds' => (int) env('SECURITY_QUEUE_HEARTBEAT_STALE_SECONDS', 180),
        'scheduler_heartbeat_stale_seconds' => (int) env('SECURITY_SCHEDULER_HEARTBEAT_STALE_SECONDS', 180),
        'require_heartbeats_in_production' => env('SECURITY_REQUIRE_HEARTBEATS_IN_PRODUCTION', true),
    ],
    'audit_retention_days' => (int) env('SECURITY_AUDIT_RETENTION_DAYS', 730),
    'sensitive_log_keys' => [
        'password',
        'token',
        'secret',
        'client_secret',
        'access_token',
        'webhook_payload',
        'private_path',
        'cube_contents',
        'zip_contents',
    ],
];
