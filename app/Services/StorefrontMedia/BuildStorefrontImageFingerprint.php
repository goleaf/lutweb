<?php

namespace App\Services\StorefrontMedia;

use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;

class BuildStorefrontImageFingerprint
{
    public function productMedia(ProductMedia $media): string
    {
        return hash('sha256', json_encode([
            'source_sha256' => $media->source_sha256,
            'pipeline_version' => config('storefront-media.pipeline_version'),
            'widths' => array_values(config('storefront-media.responsive_widths', [])),
            'jpeg_quality' => config('storefront-media.jpeg_quality'),
            'webp_quality' => config('storefront-media.webp_quality'),
            'watermark_enabled' => config('storefront-media.product_media_watermark_enabled'),
            'watermark_hash' => $this->watermarkHash((bool) config('storefront-media.product_media_watermark_enabled')),
        ], JSON_THROW_ON_ERROR));
    }

    public function productExample(ProductExample $example, ProductVersion $version, ProductFile $cube): string
    {
        return hash('sha256', json_encode([
            'source_sha256' => $example->source_sha256,
            'pipeline_version' => config('storefront-media.pipeline_version'),
            'widths' => array_values(config('storefront-media.responsive_widths', [])),
            'jpeg_quality' => config('storefront-media.jpeg_quality'),
            'webp_quality' => config('storefront-media.webp_quality'),
            'watermark_hash' => $this->watermarkHash(true),
            'product_version_id' => $version->id,
            'product_file_id' => $cube->id,
            'cube_sha256' => $cube->sha256,
            'ffmpeg_interpolation' => config('storefront-media.ffmpeg_interpolation'),
            'preview_product_id' => $example->preview_product_id,
        ], JSON_THROW_ON_ERROR));
    }

    private function watermarkHash(bool $enabled): ?string
    {
        if (! $enabled) {
            return null;
        }

        return hash('sha256', json_encode([
            'text' => config('lut-tester.watermark_text'),
            'opacity' => config('lut-tester.watermark_opacity'),
            'pattern_opacity' => config('lut-tester.watermark_pattern_opacity'),
            'spacing' => config('lut-tester.watermark_spacing'),
        ], JSON_THROW_ON_ERROR));
    }
}
