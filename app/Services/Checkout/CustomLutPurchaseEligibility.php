<?php

namespace App\Services\Checkout;

use App\Enums\CustomLutBuildFileKind;
use App\Enums\CustomLutBuildStatus;
use App\Enums\DigitalAssetKind;
use App\Enums\EntitlementStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\CustomLutCommerceSetting;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\User;
use App\Models\WizardProject;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomLutPurchaseEligibility
{
    public function __construct(
        private readonly CheckoutReadiness $readiness,
    ) {}

    public function check(CustomLutBuild $build, ?User $user): CustomLutPurchaseEligibilityResult
    {
        if (! $user instanceof User || ! $user->hasVerifiedEmail() || $user->is_suspended) {
            return CustomLutPurchaseEligibilityResult::unavailable('A verified active account is required.');
        }

        if ($build->user_id !== $user->id) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package is not available.');
        }

        if ($this->hasActiveEntitlement($user, $build)) {
            return CustomLutPurchaseEligibilityResult::owned();
        }

        $pendingOrder = $this->pendingOrder($user, $build);

        if ($pendingOrder instanceof Order) {
            return CustomLutPurchaseEligibilityResult::resume($pendingOrder);
        }

        $settings = CustomLutCommerceSetting::query()
            ->where('scope', CustomLutCommerceSetting::Scope)
            ->first();

        if (! (bool) config('custom-lut-commerce.enabled')) {
            return CustomLutPurchaseEligibilityResult::unavailable('Custom LUT purchasing is currently unavailable.');
        }

        if (! $settings instanceof CustomLutCommerceSetting || ! $settings->canAcceptNewSales()) {
            return CustomLutPurchaseEligibilityResult::unavailable('Custom LUT purchasing is currently unavailable.');
        }

        if ($settings->currency !== 'EUR') {
            return CustomLutPurchaseEligibilityResult::unavailable('Custom LUT checkout is configured for EUR only.');
        }

        if (! $this->readiness->paidCheckoutReady()) {
            return CustomLutPurchaseEligibilityResult::unavailable('PayPal checkout is not available yet.');
        }

        if ($build->expires_at !== null && $build->expires_at->isPast()) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package has expired.');
        }

        $project = $build->wizardProject;

        if ($project instanceof WizardProject) {
            if (! $project->belongsToUser($user)) {
                return CustomLutPurchaseEligibilityResult::unavailable('This LUT package is not available.');
            }

            if ($project->isExpired()) {
                return CustomLutPurchaseEligibilityResult::unavailable('This Custom LUT project has expired.');
            }

            if (! $build->is_current || $project->revision !== $build->project_revision || $project->parameters_hash !== $build->parameters_hash) {
                return CustomLutPurchaseEligibilityResult::stale();
            }
        }

        if ($build->status !== CustomLutBuildStatus::Ready) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package is not ready for purchase.');
        }

        if (! $build->sale_ready || $build->contains_draft_documents) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package is not approved for sale yet.');
        }

        if (! in_array($build->transform_version, config('custom-lut-commerce.supported_transform_versions', []), true)) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package uses an unsupported transform version.');
        }

        if (! in_array($build->generator_version, config('custom-lut-commerce.supported_generator_versions', []), true)) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package uses an unsupported generator version.');
        }

        if (! in_array($build->package_schema_version, config('custom-lut-commerce.supported_package_schema_versions', []), true)) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package uses an unsupported package schema.');
        }

        if (! $build->zip_validation_completed || ! $build->parity_validation_passed || ! $build->ffmpeg_validation_passed) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package has not passed validation.');
        }

        if (blank($build->license_version) || Str::startsWith($build->license_version, 'draft-')) {
            return CustomLutPurchaseEligibilityResult::unavailable('This LUT package must be regenerated with a final license.');
        }

        if (! $this->legalVersionsAllowNewSale($build)) {
            return CustomLutPurchaseEligibilityResult::unavailable('Package documents changed. Prepare an updated package before purchase.');
        }

        $packageFile = $this->packageFile($build);

        if (! $packageFile instanceof CustomLutBuildFile) {
            return CustomLutPurchaseEligibilityResult::unavailable('The downloadable package is not ready yet.');
        }

        return CustomLutPurchaseEligibilityResult::eligible($settings, $packageFile);
    }

    private function hasActiveEntitlement(User $user, CustomLutBuild $build): bool
    {
        return Entitlement::query()
            ->where('user_id', $user->id)
            ->where('digital_asset_kind', DigitalAssetKind::CustomLutBuild->value)
            ->where('custom_lut_build_id', $build->id)
            ->where('status', EntitlementStatus::Active->value)
            ->exists();
    }

    private function pendingOrder(User $user, CustomLutBuild $build): ?Order
    {
        $reuseAfter = now()->subMinutes((int) config('custom-lut-commerce.pending_order_reuse_minutes', 30));

        return Order::query()
            ->with(['item', 'payment'])
            ->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Processing->value])
            ->whereIn('payment_status', [PaymentStatus::Created->value, PaymentStatus::Approved->value, PaymentStatus::Pending->value])
            ->where('fulfillment_status', FulfillmentStatus::Pending->value)
            ->where('created_at', '>=', $reuseAfter)
            ->whereHas('item', function ($query) use ($build): void {
                $query
                    ->where('digital_asset_kind', DigitalAssetKind::CustomLutBuild->value)
                    ->where('custom_lut_build_id', $build->id);
            })
            ->latest()
            ->first();
    }

    private function packageFile(CustomLutBuild $build): ?CustomLutBuildFile
    {
        $file = $build->packageFile()->first();

        if (! $file instanceof CustomLutBuildFile || $file->kind !== CustomLutBuildFileKind::PackageZip) {
            return null;
        }

        if ($file->disk !== config('custom-lut-commerce.private_disk', 'private')) {
            return null;
        }

        if (! Str::startsWith($file->path, trim((string) config('custom-lut-commerce.build_prefix'), '/').'/')) {
            return null;
        }

        if ($file->size_bytes <= 0 || blank($file->sha256)) {
            return null;
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            return null;
        }

        return $file;
    }

    private function legalVersionsAllowNewSale(CustomLutBuild $build): bool
    {
        $currentLicense = config('legal.license_version');

        if (! is_string($currentLicense) || blank($currentLicense) || Str::startsWith($currentLicense, 'draft-')) {
            return ! $this->readiness->isLiveMode();
        }

        return $build->license_version === $currentLicense || ! $this->readiness->isLiveMode();
    }
}
