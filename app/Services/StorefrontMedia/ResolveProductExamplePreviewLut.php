<?php

namespace App\Services\StorefrontMedia;

use App\Enums\ProductType;
use App\Models\BundleItem;
use App\Models\Product;
use App\Models\ProductExample;
use App\Services\LutTester\ResolvedPreviewLut;
use App\Services\LutTester\ResolveProductPreviewLut;
use RuntimeException;

class ResolveProductExamplePreviewLut
{
    public function __construct(
        private readonly ResolveProductPreviewLut $resolveProductPreviewLut,
    ) {}

    public function resolve(ProductExample $example): ResolvedPreviewLut
    {
        $example->loadMissing(['product.bundleItems.product', 'previewProduct']);
        $product = $example->product;

        if (! $product instanceof Product) {
            throw new RuntimeException('Product example is missing its product.');
        }

        if (! $product->isBundle()) {
            if (! in_array($product->type, [ProductType::SingleLut, ProductType::FreeLut], true)) {
                throw new RuntimeException('Only single LUT and free LUT products can generate examples directly.');
            }

            return $this->resolveProductPreviewLut->resolve($product);
        }

        $previewProduct = $example->previewProduct;

        if (! $previewProduct instanceof Product) {
            throw new RuntimeException('Bundle examples require a preview product.');
        }

        if ($previewProduct->type === ProductType::Bundle) {
            throw new RuntimeException('Nested bundle preview products are not supported.');
        }

        $included = $product->bundleItems->contains(
            fn (BundleItem $item): bool => $item->product_id === $previewProduct->id,
        );

        if (! $included) {
            throw new RuntimeException('The selected preview product is not included in this bundle.');
        }

        return $this->resolveProductPreviewLut->resolve($previewProduct);
    }
}
