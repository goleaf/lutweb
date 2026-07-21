<?php

namespace App\Services\StorefrontMedia;

use App\Enums\StorefrontImageFormat;
use App\Enums\StorefrontImageVariantRole;
use App\Models\ProductExample;
use App\Models\ProductMedia;
use App\Models\StorefrontImageVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class StorefrontResponsiveImageFactory
{
    /**
     * @return array<string, mixed>|null
     */
    public function forMedia(?ProductMedia $media): ?array
    {
        if (! $media instanceof ProductMedia) {
            return null;
        }

        return $this->make(
            variants: $media->variants,
            role: StorefrontImageVariantRole::Media,
            altText: $media->alt_text,
            legacyDisk: $media->disk,
            legacyPath: $media->path,
            legacyWidth: $media->width,
            legacyHeight: $media->height,
            credit: $media->source_credit_is_public ? $media->source_credit : null,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function before(ProductExample $example): ?array
    {
        return $this->make(
            variants: $example->variants,
            role: StorefrontImageVariantRole::Before,
            altText: $example->before_alt_text,
            legacyDisk: $example->before_disk,
            legacyPath: $example->before_path,
            legacyWidth: $example->source_width,
            legacyHeight: $example->source_height,
            credit: $example->source_credit_is_public ? $example->source_credit : null,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function after(ProductExample $example): ?array
    {
        return $this->make(
            variants: $example->variants,
            role: StorefrontImageVariantRole::After,
            altText: $example->after_alt_text,
            legacyDisk: $example->after_disk,
            legacyPath: $example->after_path,
            legacyWidth: $example->source_width,
            legacyHeight: $example->source_height,
            credit: $example->source_credit_is_public ? $example->source_credit : null,
        );
    }

    /**
     * @param  Collection<int, StorefrontImageVariant>  $variants
     * @return array<string, mixed>|null
     */
    private function make(
        Collection $variants,
        StorefrontImageVariantRole $role,
        string $altText,
        ?string $legacyDisk,
        ?string $legacyPath,
        ?int $legacyWidth,
        ?int $legacyHeight,
        ?string $credit = null,
    ): ?array {
        $publicVariants = $variants
            ->filter(fn (StorefrontImageVariant $variant): bool => $variant->role === $role && $variant->isPublicDerivative())
            ->sortBy('width')
            ->values();

        $jpeg = $publicVariants->where('format', StorefrontImageFormat::Jpeg)->values();
        $webp = $publicVariants->where('format', StorefrontImageFormat::Webp)->values();
        $fallback = $jpeg->last();

        if ($fallback instanceof StorefrontImageVariant) {
            return [
                'alt_text' => $altText,
                'aspect_ratio' => $fallback->width.'/'.$fallback->height,
                'fallback_jpeg_url' => $fallback->publicUrl(),
                'webp_srcset' => $this->srcset($webp),
                'jpeg_srcset' => $this->srcset($jpeg),
                'width' => $fallback->width,
                'height' => $fallback->height,
                'placeholder_color' => '#292524',
                'credit' => $credit,
            ];
        }

        if ($legacyDisk === (string) config('storefront-media.public_disk', 'public') && is_string($legacyPath) && $legacyPath !== '') {
            return [
                'alt_text' => $altText,
                'aspect_ratio' => $legacyWidth !== null && $legacyHeight !== null && $legacyHeight > 0 ? $legacyWidth.'/'.$legacyHeight : '4/3',
                'fallback_jpeg_url' => Storage::disk((string) config('storefront-media.public_disk', 'public'))->url($legacyPath),
                'webp_srcset' => '',
                'jpeg_srcset' => Storage::disk((string) config('storefront-media.public_disk', 'public'))->url($legacyPath).($legacyWidth ? ' '.$legacyWidth.'w' : ''),
                'width' => $legacyWidth,
                'height' => $legacyHeight,
                'placeholder_color' => '#292524',
                'credit' => $credit,
            ];
        }

        return null;
    }

    /**
     * @param  Collection<int, StorefrontImageVariant>  $variants
     */
    private function srcset(Collection $variants): string
    {
        return $variants
            ->map(fn (StorefrontImageVariant $variant): string => $variant->publicUrl().' '.$variant->width.'w')
            ->implode(', ');
    }
}
