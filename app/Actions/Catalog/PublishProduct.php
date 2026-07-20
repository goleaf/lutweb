<?php

namespace App\Actions\Catalog;

use App\Enums\ProductFileKind;
use App\Enums\ProductMediaKind;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublishProduct
{
    public function handle(Product $product, ?CarbonInterface $publishedAt = null): Product
    {
        $this->validate($product);

        return DB::transaction(function () use ($product, $publishedAt): Product {
            $product->forceFill([
                'status' => ProductStatus::Published,
                'published_at' => $publishedAt ?? now(),
            ])->save();

            return $product->refresh();
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

        if ($product->media()->where('kind', ProductMediaKind::Cover)->count() !== 1) {
            $errors['cover'] = 'Add exactly one cover image before publishing.';
        }

        if ($product->examples()->where('is_active', true)->count() === 0) {
            $errors['examples'] = 'Add at least one active before and after example before publishing.';
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
