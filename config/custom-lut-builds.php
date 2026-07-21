<?php

use App\Enums\CubeGeneratorVersion;
use App\Enums\LutTransformVersion;

return [
    'enabled' => (bool) env('CUSTOM_LUT_BUILDS_ENABLED', true),
    'private_disk' => env('CUSTOM_LUT_PRIVATE_DISK', 'private'),
    'queue' => env('CUSTOM_LUT_BUILD_QUEUE', 'images'),
    'transform_version' => LutTransformVersion::V1->value,
    'generator_version' => CubeGeneratorVersion::V1->value,
    'package_schema_version' => 'lut-web-custom-package-v1',
    'cube_sizes' => array_values(array_filter(
        array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', (string) env('CUSTOM_LUT_CUBE_SIZES', '17,33,65')),
        ),
        static fn (int $value): bool => $value > 0,
    )),
    'cube_precision' => (int) env('CUSTOM_LUT_CUBE_PRECISION', 9),
    'cube_domain_minimum' => 0.0,
    'cube_domain_maximum' => 1.0,
    'build_expiration_days' => (int) env('CUSTOM_LUT_BUILD_TTL_DAYS', 7),
    'maximum_builds_per_project_per_hour' => (int) env('CUSTOM_LUT_BUILDS_PER_PROJECT_PER_HOUR', 5),
    'maximum_builds_per_user_per_day' => (int) env('CUSTOM_LUT_BUILDS_PER_USER_PER_DAY', 20),
    'maximum_package_size_bytes' => (int) env('CUSTOM_LUT_MAX_PACKAGE_BYTES', 104_857_600),
    'maximum_uncompressed_zip_size_bytes' => (int) env('CUSTOM_LUT_MAX_UNCOMPRESSED_BYTES', 157_286_400),
    'ffmpeg_validation_enabled' => (bool) env('CUSTOM_LUT_FFMPEG_VALIDATION', true),
    'ffmpeg_binary' => env('CUSTOM_LUT_FFMPEG_BINARY', 'ffmpeg'),
    'ffmpeg_timeout' => (int) env('CUSTOM_LUT_FFMPEG_TIMEOUT', 45),
    'ffmpeg_interpolation' => env('CUSTOM_LUT_FFMPEG_INTERPOLATION', 'tetrahedral'),
    'allow_draft_documents' => (bool) env('CUSTOM_LUT_ALLOW_DRAFT_DOCUMENTS', env('APP_ENV') !== 'production'),
    'build_prefix' => trim((string) env('CUSTOM_LUT_BUILD_PREFIX', 'custom-lut-builds'), '/'),
    'work_prefix' => trim((string) env('CUSTOM_LUT_BUILD_WORK_PREFIX', 'custom-lut-build-work'), '/'),
    'parity_sample_count' => max(4096, (int) env('CUSTOM_LUT_PARITY_SAMPLE_COUNT', 4096)),
    'parity_thresholds' => [
        'lattice_max_millionths' => 500_000,
        'between_mean_millionths' => 2_750_000,
        'between_p95_millionths' => 7_500_000,
        'between_p99_millionths' => 11_500_000,
        'between_max_millionths' => 17_500_000,
    ],
    'prune_batch_size' => (int) env('CUSTOM_LUT_BUILD_PRUNE_BATCH_SIZE', 200),
];
