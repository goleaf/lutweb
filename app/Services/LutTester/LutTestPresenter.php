<?php

namespace App\Services\LutTester;

use App\Models\LutTestUpload;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Support\Catalog\EurMoney;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class LutTestPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function product(Product $product): array
    {
        $product->loadMissing('coverMedia');

        return [
            'name' => $product->name,
            'slug' => $product->slug,
            'url' => route('shop.show', $product->slug),
            'try_url' => route('shop.tester.create', $product->slug),
            'short_description' => $product->short_description,
            'formatted_price' => $product->isFree() ? 'Free' : '€'.EurMoney::formatCents($product->price_cents),
            'is_free' => $product->isFree(),
            'cover' => $product->coverMedia instanceof ProductMedia ? [
                'url' => Storage::disk('public')->url($product->coverMedia->path),
                'alt_text' => $product->coverMedia->alt_text,
                'width' => $product->coverMedia->width,
                'height' => $product->coverMedia->height,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function test(?LutTestUpload $upload): ?array
    {
        if (! $upload instanceof LutTestUpload) {
            return null;
        }

        $status = $upload->isExpired() ? 'expired' : $upload->status->value;
        $isReady = $status === 'ready' && $upload->before_preview_path !== null && $upload->after_preview_path !== null;

        return [
            'id' => $upload->id,
            'status' => $status,
            'original_name' => $upload->original_name,
            'preview_width' => $upload->preview_width,
            'preview_height' => $upload->preview_height,
            'created_at' => $upload->created_at?->toISOString(),
            'expires_at' => $upload->expires_at->toISOString(),
            'failure_message' => $upload->isFailed()
                ? ($upload->failure_message ?: 'We could not process this image.')
                : null,
            'before_url' => $isReady ? $this->imageUrl($upload, 'before') : null,
            'after_url' => $isReady ? $this->imageUrl($upload, 'after') : null,
            'delete_url' => route('shop.tester.destroy', [
                'slug' => $upload->product->slug,
                'lutTestUpload' => $upload->id,
            ]),
            'can_delete' => true,
        ];
    }

    private function imageUrl(LutTestUpload $upload, string $variant): ?string
    {
        if ($upload->isExpired()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'lut-tests.images.show',
            $upload->expires_at,
            [
                'lutTestUpload' => $upload->id,
                'variant' => $variant,
            ],
        );
    }
}
