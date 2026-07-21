<?php

namespace App\Services\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductFile;
use App\Notifications\LutReadyForDownload;
use App\Notifications\OrderPaymentConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FulfillPaidOrder
{
    public function __construct(
        private readonly GrantOrderEntitlement $grantEntitlement,
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
            $file = $item instanceof OrderItem && $item->product_file_id !== null
                ? ProductFile::query()->with('productVersion')->find($item->product_file_id)
                : null;

            if (! $this->fileCanBeFulfilled($item, $file)) {
                $lockedOrder->forceFill([
                    'status' => OrderStatus::NeedsReview,
                    'fulfillment_status' => FulfillmentStatus::Failed,
                ])->save();

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
            $shouldNotify = true;

            return $lockedOrder->refresh()->load(['entitlement', 'user']);
        });

        if ($shouldNotify && $fulfilled->user !== null) {
            $fulfilled->user->notify(new OrderPaymentConfirmed($fulfilled));
            $fulfilled->user->notify(new LutReadyForDownload($fulfilled));
        }

        return $fulfilled;
    }

    private function fileCanBeFulfilled(?OrderItem $item, ?ProductFile $file): bool
    {
        return $item instanceof OrderItem
            && $file instanceof ProductFile
            && $file->kind === ProductFileKind::PackageZip
            && $file->disk === 'private'
            && $file->product_version_id === $item->product_version_id
            && Storage::disk('private')->exists($file->path);
    }
}
