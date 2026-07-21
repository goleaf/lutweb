<?php

namespace App\Actions\Catalog;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProductFileKind;
use App\Enums\ProductMediaKind;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Enums\StorefrontImageFormat;
use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductMedia;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublishProduct
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Product $product, ?CarbonInterface $publishedAt = null): Product
    {
        $this->validate($product);

        return DB::transaction(function () use ($product, $publishedAt): Product {
            $product->forceFill([
                'status' => ProductStatus::Published,
                'published_at' => $publishedAt ?? now(),
            ])->save();

            $product = $product->refresh();
            $actor = request()->user();
            $this->audit->handle('product.published', actor: $actor instanceof User ? $actor : null, auditable: $product);

            return $product;
        });
    }

    protected function validate(Product $product): void
    {
        $errors = [];

        if (Str::of($product->name)->trim()->isEmpty()) {
            $errors['name'] = 'A product name is required before publishing.';
        }

        if (Str::of($product->slug)->trim()->isEmpty()) {
            $errors['slug'] = 'A product slug is required before publishing.';
        }

        if (Str::of($product->short_description)->trim()->isEmpty()) {
            $errors['short_description'] = 'A short description is required before publishing.';
        }

        if ($product->currency !== 'EUR') {
            $errors['currency'] = 'Catalog products must use EUR.';
        }

        if ($product->categories()->count() === 0) {
            $errors['categories'] = 'Add at least one category before publishing.';
        }

        $covers = $product->media()
            ->where('kind', ProductMediaKind::Cover)
            ->with('variants')
            ->get();

        if ($covers->count() !== 1) {
            $errors['cover'] = 'Add exactly one cover image before publishing.';
        } else {
            $cover = $covers->first();

            if (! $cover instanceof ProductMedia || ! $cover->isReady()) {
                $errors['cover'] = 'The cover image must be ready before publishing.';
            } elseif (Str::of($cover->alt_text)->trim()->isEmpty()) {
                $errors['cover_alt_text'] = 'The cover image needs English alt text before publishing.';
            } elseif (! $cover->hasConfirmedUsageRights()) {
                $errors['cover_rights'] = 'Confirm usage rights for the cover image before publishing.';
            } elseif (! $this->hasResponsiveVariantPair($cover, StorefrontImageVariantRole::Media)) {
                $errors['cover_derivatives'] = 'The cover image needs verified JPEG and WebP storefront derivatives before publishing.';
            }
        }

        $examples = $product->examples()
            ->where('is_active', true)
            ->with('variants')
            ->get();

        if ($examples->count() === 0) {
            $errors['examples'] = 'Add at least one active before and after example before publishing.';
        } elseif (! $examples->contains(fn (ProductExample $example): bool => $this->exampleIsPublishable($example))) {
            $errors['examples'] = 'Add at least one ready active example with confirmed rights, alt text and JPEG/WebP derivatives before publishing.';
        }

        $currentVersion = $product->currentVersion()->with('files')->first();

        if (! $currentVersion || $currentVersion->status !== ProductVersionStatus::Ready) {
            $errors['current_version'] = 'Select a current ready version before publishing.';
        } elseif (! $currentVersion->files->contains('kind', ProductFileKind::PackageZip)) {
            $errors['package_zip'] = 'The current version requires a package ZIP file before publishing.';
        }

        $this->validatePrice($product, $errors);
        $this->validateBundle($product, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function exampleIsPublishable(ProductExample $example): bool
    {
        return $example->processing_status === StorefrontImageStatus::Ready
            && $example->hasConfirmedUsageRights()
            && Str::of($example->before_alt_text)->trim()->isNotEmpty()
            && Str::of($example->after_alt_text)->trim()->isNotEmpty()
            && $this->hasResponsiveVariantPair($example, StorefrontImageVariantRole::Before)
            && $this->hasResponsiveVariantPair($example, StorefrontImageVariantRole::After);
    }

    private function hasResponsiveVariantPair(ProductMedia|ProductExample $imageable, StorefrontImageVariantRole $role): bool
    {
        $variants = $imageable->variants
            ->filter(fn ($variant): bool => $variant->role === $role && $variant->isPublicDerivative());

        return $variants->contains('format', StorefrontImageFormat::Jpeg)
            && $variants->contains('format', StorefrontImageFormat::Webp);
    }

    /**
     * @param  array<string, string>  $errors
     */
    protected function validatePrice(Product $product, array &$errors): void
    {
        if ($product->type === ProductType::FreeLut && $product->price_cents !== 0) {
            $errors['price_cents'] = 'Free LUT products must have a zero EUR price.';

            return;
        }

        if (in_array($product->type, [ProductType::SingleLut, ProductType::Bundle], true)
            && $product->price_cents <= 0) {
            $errors['price_cents'] = 'Paid LUT products must have a EUR price greater than zero.';
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    protected function validateBundle(Product $product, array &$errors): void
    {
        if (! $product->isBundle()) {
            return;
        }

        $items = $product->bundleItems()->with('product')->get();

        if ($items->count() < 2) {
            $errors['bundle_items'] = 'Bundles must contain at least two products.';

            return;
        }

        if ($items->pluck('product_id')->duplicates()->isNotEmpty()) {
            $errors['bundle_items'] = 'Bundles cannot contain duplicate products.';
        }

        if ($items->contains('product_id', $product->id)) {
            $errors['bundle_items'] = 'A bundle cannot contain itself.';
        }

        if ($items->contains(fn ($item): bool => $item->product?->type === ProductType::Bundle)) {
            $errors['bundle_items'] = 'Nested bundles are not allowed.';
        }

        if ($items->contains(fn ($item): bool => ! in_array($item->product?->type, [ProductType::SingleLut, ProductType::FreeLut], true))) {
            $errors['bundle_items'] = 'Bundle items must be single LUT or free LUT products.';
        }
    }
}
