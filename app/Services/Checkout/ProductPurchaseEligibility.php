<?php

namespace App\Services\Checkout;

use App\Enums\ProductFileKind;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Models\Entitlement;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ProductPurchaseEligibility
{
    public function __construct(
        private readonly CheckoutReadiness $readiness,
    ) {}

    public function check(Product $product, ?User $user = null): PurchaseEligibilityResult
    {
        if (! $product->isPublished() || $product->trashed()) {
            return PurchaseEligibilityResult::unavailable('This LUT is not available for purchase.');
        }

        if ($user?->is_suspended) {
            return PurchaseEligibilityResult::unavailable('This account cannot make purchases.');
        }

        if ($user instanceof User && $this->hasActiveEntitlement($user, $product)) {
            return PurchaseEligibilityResult::owned();
        }

        $package = $this->resolvePackage($product);

        if ($package === null) {
            return PurchaseEligibilityResult::unavailable('The downloadable package is not ready yet.');
        }

        if ($product->currency !== 'EUR') {
            return PurchaseEligibilityResult::unavailable('This product is not configured for EUR checkout.');
        }

        if ($product->type === ProductType::FreeLut) {
            if ($product->price_cents !== 0) {
                return PurchaseEligibilityResult::unavailable('This free LUT is not configured correctly.');
            }

            if (! $this->readiness->freeCheckoutReady()) {
                return PurchaseEligibilityResult::unavailable('Claiming is not available yet.');
            }

            return PurchaseEligibilityResult::claim($package);
        }

        if (! in_array($product->type, [ProductType::SingleLut, ProductType::Bundle], true)) {
            return PurchaseEligibilityResult::unavailable('This product type is not available for checkout.');
        }

        if ($product->price_cents <= 0) {
            return PurchaseEligibilityResult::unavailable('This paid LUT is not configured with a valid price.');
        }

        if (! $this->readiness->paidCheckoutReady()) {
            return PurchaseEligibilityResult::unavailable('PayPal checkout is not available yet.');
        }

        return PurchaseEligibilityResult::buy($package);
    }

    public function resolvePackage(Product $product): ?PurchasablePackage
    {
        $product->loadMissing('currentVersion.files');
        $version = $product->currentVersion;

        if ($version === null || $version->status !== ProductVersionStatus::Ready) {
            return null;
        }

        $files = $version->files
            ->filter(fn (ProductFile $file): bool => $file->kind === ProductFileKind::PackageZip)
            ->values();

        if ($files->count() !== 1) {
            return null;
        }

        $file = $files->first();

        if (! $file instanceof ProductFile || $file->disk !== 'private') {
            return null;
        }

        if (! Storage::disk('private')->exists($file->path)) {
            return null;
        }

        return new PurchasablePackage($version, $file);
    }

    private function hasActiveEntitlement(User $user, Product $product): bool
    {
        return Entitlement::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();
    }
}
