<?php

namespace App\Services\Checkout;

use App\Enums\CustomLutBuildFileKind;
use App\Enums\EntitlementStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Models\CustomLutBuildFile;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FulfillPaidOrder
{
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()
                ->with(['item', 'payment'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->fulfillment_status === FulfillmentStatus::Ready) {
                return $lockedOrder;
            }

            $item = $lockedOrder->item;
            $payment = $lockedOrder->payment;

            if (! $item instanceof OrderItem || ! $payment instanceof Payment || $payment->status !== PaymentStatus::Completed) {
                return $lockedOrder;
            }

            if ($payment->amount_cents !== $lockedOrder->total_cents || $payment->currency !== 'EUR') {
                $this->markFulfillmentFailed($lockedOrder, 'payment_amount_mismatch');

                return $lockedOrder;
            }

            if ($item->isCustomLutBuild() && ! $this->customLutPackageValid($item)) {
                $this->markFulfillmentFailed($lockedOrder, 'custom_lut_package_integrity_failed');

                return $lockedOrder;
            }

            if ($item->isCatalogProduct() && ! $this->catalogPackageValid($item)) {
                $this->markFulfillmentFailed($lockedOrder, 'catalog_package_integrity_failed');

                return $lockedOrder;
            }

            $now = now();

            Entitlement::query()->firstOrCreate(
                ['order_item_id' => $item->id],
                [
                    'user_id' => $lockedOrder->user_id,
                    'digital_asset_kind' => $item->digital_asset_kind,
                    'order_id' => $lockedOrder->id,
                    'product_id' => $item->product_id,
                    'product_version_id' => $item->product_version_id,
                    'product_file_id' => $item->product_file_id,
                    'wizard_project_id' => $item->wizard_project_id,
                    'custom_lut_build_id' => $item->custom_lut_build_id,
                    'custom_lut_build_file_id' => $item->custom_lut_build_file_id,
                    'status' => EntitlementStatus::Active,
                    'granted_at' => $now,
                ],
            );

            $lockedOrder->forceFill([
                'status' => OrderStatus::Completed,
                'payment_status' => PaymentStatus::Completed,
                'fulfillment_status' => FulfillmentStatus::Ready,
                'paid_at' => $lockedOrder->paid_at ?? $payment->completed_at ?? $now,
                'fulfilled_at' => $lockedOrder->fulfilled_at ?? $now,
            ])->save();

            if ($item->isCustomLutBuild() && $item->customLutBuild !== null) {
                $item->customLutBuild->forceFill([
                    'purchased_at' => $item->customLutBuild->purchased_at ?? $now,
                    'locked_at' => $item->customLutBuild->locked_at ?? $now,
                ])->save();
            }

            return $lockedOrder->refresh()->load(['item', 'payment', 'entitlement']);
        }, attempts: 3);
    }

    private function customLutPackageValid(OrderItem $item): bool
    {
        $file = $item->customLutBuildFile;
        $build = $item->customLutBuild;

        if (
            ! $file instanceof CustomLutBuildFile
            || $build === null
            || $file->custom_lut_build_id !== $item->custom_lut_build_id
            || $file->kind !== CustomLutBuildFileKind::PackageZip
            || $file->disk !== config('custom-lut-commerce.private_disk', 'private')
            || $file->size_bytes !== $item->custom_lut_package_size_bytes
            || $file->sha256 !== $item->custom_lut_package_sha256
            || $build->build_fingerprint !== $item->custom_lut_build_fingerprint
            || $build->parameters_hash !== $item->custom_lut_parameters_hash
            || $build->transform_version !== $item->custom_lut_transform_version
            || $build->generator_version !== $item->custom_lut_generator_version
            || $build->package_schema_version !== $item->custom_lut_package_schema_version
        ) {
            return false;
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            return false;
        }

        return ! (bool) config('custom-lut-commerce.verify_package_hash_on_fulfillment', true)
            || $this->sha256($file->disk, $file->path) === $item->custom_lut_package_sha256;
    }

    private function catalogPackageValid(OrderItem $item): bool
    {
        $file = $item->productFile;

        return $file instanceof ProductFile
            && $file->kind === ProductFileKind::PackageZip
            && $file->disk === 'private'
            && Storage::disk($file->disk)->exists($file->path);
    }

    private function markFulfillmentFailed(Order $order, string $failureCode): void
    {
        $order->forceFill([
            'status' => OrderStatus::NeedsReview,
            'fulfillment_status' => FulfillmentStatus::Failed,
        ])->save();

        $order->payment?->forceFill([
            'failure_code' => $failureCode,
        ])->save();
    }

    private function sha256(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if ($stream === false) {
            return '';
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return hash_final($context);
    }
}
