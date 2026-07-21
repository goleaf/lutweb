<?php

namespace App\Services\Orders;

use App\Actions\Notifications\DispatchNotificationOnce;
use App\Enums\CustomLutBuildFileKind;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Models\CustomLutBuildFile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductFile;
use App\Notifications\LutReadyForDownload;
use App\Notifications\OrderPaymentConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FulfillPaidOrder
{
    public function __construct(
        private readonly GrantOrderEntitlement $grantEntitlement,
        private readonly DispatchNotificationOnce $dispatchNotificationOnce,
    ) {}

    public function handle(Order $order): Order
    {
        $shouldNotify = false;

        $fulfilled = DB::transaction(function () use ($order, &$shouldNotify): Order {
            $lockedOrder = Order::query()
                ->with(['item', 'payment', 'entitlement', 'user'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $payment = $lockedOrder->payment;

            if (! $payment instanceof Payment || $payment->status !== PaymentStatus::Completed) {
                return $lockedOrder;
            }

            if ($lockedOrder->fulfillment_status === FulfillmentStatus::Revoked) {
                return $lockedOrder;
            }

            if ($lockedOrder->fulfillment_status === FulfillmentStatus::Ready && $lockedOrder->entitlement !== null) {
                return $lockedOrder;
            }

            $item = $lockedOrder->item;
            if ($payment->amount_cents !== $lockedOrder->total_cents || $payment->currency !== 'EUR') {
                $this->markFulfillmentFailed($lockedOrder, 'payment_amount_mismatch');

                return $lockedOrder;
            }

            if (! $this->packageCanBeFulfilled($item)) {
                $this->markFulfillmentFailed($lockedOrder, 'package_integrity_failed');

                return $lockedOrder;
            }

            $now = now();

            $lockedOrder->forceFill([
                'status' => OrderStatus::Completed,
                'payment_status' => PaymentStatus::Completed,
                'fulfillment_status' => FulfillmentStatus::Ready,
                'paid_at' => $lockedOrder->paid_at ?? $payment->completed_at ?? $now,
                'fulfilled_at' => $lockedOrder->fulfilled_at ?? $now,
            ])->save();

            $this->grantEntitlement->handle($lockedOrder);

            if ($item instanceof OrderItem && $item->isCustomLutBuild() && $item->customLutBuild !== null) {
                $item->customLutBuild->forceFill([
                    'locked_at' => $item->customLutBuild->locked_at ?? $now,
                    'purchased_at' => $item->customLutBuild->purchased_at ?? $now,
                ])->save();
            }

            $shouldNotify = true;

            return $lockedOrder->refresh()->load(['entitlement', 'user']);
        });

        if ($shouldNotify && $fulfilled->user !== null) {
            $this->dispatchNotificationOnce->handle(
                eventKey: 'order:'.$fulfilled->id.':payment-confirmed',
                user: $fulfilled->user,
                notification: new OrderPaymentConfirmed($fulfilled),
                related: $fulfilled,
            );
            $this->dispatchNotificationOnce->handle(
                eventKey: 'order:'.$fulfilled->id.':download-ready',
                user: $fulfilled->user,
                notification: new LutReadyForDownload($fulfilled),
                related: $fulfilled,
            );
        }

        return $fulfilled;
    }

    private function packageCanBeFulfilled(?OrderItem $item): bool
    {
        if (! $item instanceof OrderItem) {
            return false;
        }

        return $item->isCustomLutBuild()
            ? $this->customLutPackageCanBeFulfilled($item)
            : $this->catalogPackageCanBeFulfilled($item);
    }

    private function catalogPackageCanBeFulfilled(OrderItem $item): bool
    {
        $file = $item->product_file_id !== null
            ? ProductFile::query()->with('productVersion')->find($item->product_file_id)
            : null;

        return $file instanceof ProductFile
            && $file->kind === ProductFileKind::PackageZip
            && $file->disk === 'private'
            && $file->product_version_id === $item->product_version_id
            && Storage::disk('private')->exists($file->path);
    }

    private function customLutPackageCanBeFulfilled(OrderItem $item): bool
    {
        $item->loadMissing(['customLutBuild', 'customLutBuildFile']);

        $build = $item->customLutBuild;
        $file = $item->customLutBuildFile;

        if (
            ! $file instanceof CustomLutBuildFile
            || $build === null
            || $item->product_id !== null
            || $item->product_version_id !== null
            || $item->product_file_id !== null
            || $item->custom_lut_build_id === null
            || $item->custom_lut_build_file_id === null
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

        $prefix = trim((string) config('custom-lut-commerce.build_prefix'), '/').'/';

        if (! Str::startsWith($file->path, $prefix) || ! Storage::disk($file->disk)->exists($file->path)) {
            return false;
        }

        if (! (bool) config('custom-lut-commerce.verify_package_hash_on_fulfillment', true)) {
            return true;
        }

        return $this->sha256($file->disk, $file->path) === $item->custom_lut_package_sha256;
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

        if (! is_resource($stream)) {
            return '';
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
