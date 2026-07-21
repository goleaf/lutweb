<?php

return [
    'enabled' => env('SEO_ENABLED', true),
    'site_name' => env('SEO_SITE_NAME', 'LUT Web'),
    'canonical_url' => rtrim((string) env('SEO_CANONICAL_URL', env('APP_URL', '')), '/'),
    'default_title' => env('SEO_DEFAULT_TITLE', 'Professional LUTs for Photographers and Creators'),
    'title_suffix' => env('SEO_TITLE_SUFFIX', ' - LUT Web'),
    'default_description' => env('SEO_DEFAULT_DESCRIPTION', 'Try professional LUTs on your photos, create custom looks, and securely download your purchases.'),
    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE'),
    'indexing_enabled' => env('SEO_INDEXING_ENABLED', false),
    'sitemap_cache_lifetime' => (int) env('SEO_SITEMAP_CACHE_SECONDS', 3600),
    'maximum_urls_per_sitemap' => min(50_000, (int) env('SEO_SITEMAP_MAX_URLS', 50_000)),
    'robots_behavior' => env('SEO_ROBOTS_BEHAVIOR', 'config'),
    'public_cdn_host' => env('SEO_PUBLIC_CDN_HOST'),
    'organization_name' => env('SEO_ORGANIZATION_NAME', 'LUT Web'),
    'organization_logo' => env('SEO_ORGANIZATION_LOGO'),
    'support_email' => env('SEO_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
    'social_profiles' => array_values(array_filter(array_map('trim', explode(',', (string) env('SEO_SOCIAL_PROFILES', ''))))),
];
