<?php

namespace App\Services\LutTester;

use App\Enums\ProductFileKind;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Models\Product;
use App\Models\ProductFile;

class ProductLutTestEligibility
{
    public function canTest(Product $product): bool
    {
        if (! (bool) config('lut-tester.enabled', true)) {
            return false;
        }

        if (! $product->isPublished() || $product->trashed()) {
            return false;
        }

        if (! in_array($product->type, [ProductType::SingleLut, ProductType::FreeLut], true)) {
            return false;
        }

        if ($product->isBundle() || ! $product->is_testable) {
            return false;
        }

        $product->loadMissing('currentVersion.files');
        $version = $product->currentVersion;

        if ($version === null || $version->status !== ProductVersionStatus::Ready) {
            return false;
        }

        return $version->files
            ->contains(fn (ProductFile $file): bool => in_array($file->kind, $this->supportedKinds(), true)
                && $file->disk === (string) config('lut-tester.disk', 'private'));
    }

    /**
     * @return array<int, ProductFileKind>
     */
    private function supportedKinds(): array
    {
        return [
            ProductFileKind::Cube33,
            ProductFileKind::Cube65,
            ProductFileKind::Cube17,
            ProductFileKind::SourceCube,
        ];
    }
}
