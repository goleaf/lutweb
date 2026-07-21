<?php

return [
    'enabled' => (bool) env('CUSTOM_LUT_COMMERCE_ENABLED', false),
    'currency' => 'EUR',
    'checkout_route_enabled' => true,
    'payment_queue' => env('CUSTOM_LUT_PAYMENT_QUEUE', env('PAYPAL_PAYMENT_QUEUE', 'payments')),
    'rate_limits' => [
        'checkout_per_minute' => (int) env('CUSTOM_LUT_CHECKOUT_ATTEMPTS_PER_MINUTE', 10),
        'capture_per_minute' => (int) env('CUSTOM_LUT_CAPTURE_ATTEMPTS_PER_MINUTE', 10),
        'downloads_per_ten_minutes' => (int) env('CUSTOM_LUT_DOWNLOADS_PER_TEN_MINUTES', 10),
        'integrity_checks_per_hour' => (int) env('CUSTOM_LUT_INTEGRITY_CHECKS_PER_HOUR', 3),
    ],
    'max_active_unpaid_orders_per_build' => (int) env('CUSTOM_LUT_MAX_ACTIVE_UNPAID_ORDERS_PER_BUILD', 1),
    'max_active_unpaid_orders_per_user' => (int) env('CUSTOM_LUT_MAX_ACTIVE_UNPAID_ORDERS', 5),
    'verify_package_integrity' => (bool) env('CUSTOM_LUT_VERIFY_PACKAGE_INTEGRITY', true),
    'verify_package_hash_on_fulfillment' => (bool) env('CUSTOM_LUT_VERIFY_PACKAGE_HASH_ON_FULFILLMENT', true),
    'verify_package_hash_on_download' => (bool) env('CUSTOM_LUT_VERIFY_PACKAGE_HASH_ON_DOWNLOAD', false),
    'verify_package_metadata_on_download' => true,
    'order_build_retention' => 'retain_order_referenced_builds',
    'pending_order_reuse_minutes' => (int) env('CUSTOM_LUT_PENDING_ORDER_REUSE_MINUTES', 30),
    'support_email' => env('CUSTOM_LUT_SUPPORT_EMAIL'),
    'private_disk' => env('CUSTOM_LUT_PRIVATE_DISK', 'private'),
    'build_prefix' => trim((string) env('CUSTOM_LUT_BUILD_PREFIX', 'custom-lut-builds'), '/'),
    'supported_transform_versions' => ['lut_transform_v1', 'v1'],
    'supported_generator_versions' => ['v1'],
    'supported_package_schema_versions' => ['v1'],
    'doctor_requirements' => [
        'final_legal_versions' => true,
        'private_disk' => true,
        'paypal' => true,
        'checkout_routes' => true,
    ],
];
