<?php

use App\Enums\StorefrontMediaPipelineVersion;

return [
    'enabled' => env('STOREFRONT_MEDIA_ENABLED', true),
    'private_disk' => env('STOREFRONT_MEDIA_PRIVATE_DISK', 'private'),
    'public_disk' => env('STOREFRONT_MEDIA_PUBLIC_DISK', 'public'),
    'queue' => env('STOREFRONT_MEDIA_QUEUE', 'images'),
    'pipeline_version' => StorefrontMediaPipelineVersion::V1->value,
    'maximum_upload_bytes' => (int) env('STOREFRONT_MEDIA_MAX_UPLOAD_MB', 30) * 1024 * 1024,
    'maximum_pixels' => (int) env('STOREFRONT_MEDIA_MAX_PIXELS', 60_000_000),
    'maximum_edge' => (int) env('STOREFRONT_MEDIA_MAX_EDGE', 14_000),
    'minimum_width' => (int) env('STOREFRONT_MEDIA_MIN_WIDTH', 480),
    'minimum_height' => (int) env('STOREFRONT_MEDIA_MIN_HEIGHT', 480),
    'normalized_master_maximum_edge' => (int) env('STOREFRONT_MEDIA_MASTER_MAX_EDGE', 2_400),
    'responsive_widths' => [480, 768, 1200, 1600],
    'jpeg_quality' => (int) env('STOREFRONT_MEDIA_JPEG_QUALITY', 84),
    'webp_quality' => (int) env('STOREFRONT_MEDIA_WEBP_QUALITY', 82),
    'example_watermark_enabled' => env('STOREFRONT_EXAMPLE_WATERMARK', true),
    'product_media_watermark_enabled' => env('STOREFRONT_PRODUCT_MEDIA_WATERMARK', false),
    'public_prefix' => trim((string) env('STOREFRONT_MEDIA_PUBLIC_PREFIX', 'storefront'), '/'),
    'private_source_prefix' => trim((string) env('STOREFRONT_MEDIA_PRIVATE_PREFIX', 'storefront-sources'), '/'),
    'temporary_work_prefix' => trim((string) env('STOREFRONT_MEDIA_WORK_PREFIX', 'storefront-work'), '/'),
    'processing_timeout' => (int) env('STOREFRONT_MEDIA_PROCESSING_TIMEOUT', 90),
    'ffmpeg_timeout' => (int) env('STOREFRONT_MEDIA_FFMPEG_TIMEOUT', 90),
    'ffmpeg_interpolation' => env('STOREFRONT_MEDIA_FFMPEG_INTERPOLATION', 'tetrahedral'),
    'retry_count' => (int) env('STOREFRONT_MEDIA_RETRY_COUNT', 2),
    'prune_batch_size' => (int) env('STOREFRONT_MEDIA_PRUNE_BATCH_SIZE', 500),
    'stale_source_policy' => env('STOREFRONT_MEDIA_STALE_SOURCE_POLICY', 'retain_last_ready'),
];
