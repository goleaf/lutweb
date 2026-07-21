<?php

namespace App\Services\Orders;

use App\Enums\EntitlementStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class RevokeOrderEntitlement
{
    public function handle(Order $order, PaymentStatus $paymentStatus, string $reason): Order
    {
        return DB::transaction(function () use ($order, $paymentStatus, $reason): Order {
            $lockedOrder = Order::query()
                ->with(['payment', 'entitlement'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->payment !== null) {
                $lockedOrder->payment->forceFill([
                    'status' => $paymentStatus,
                    'reversed_at' => $paymentStatus === PaymentStatus::Reversed ? now() : $lockedOrder->payment->reversed_at,
                    'refunded_at' => $paymentStatus === PaymentStatus::Refunded ? now() : $lockedOrder->payment->refunded_at,
                ])->save();
            }

            if ($lockedOrder->entitlement !== null && $lockedOrder->entitlement->status === EntitlementStatus::Active) {
                $lockedOrder->entitlement->forceFill([
                    'status' => EntitlementStatus::Revoked,
                    'revoked_at' => now(),
                    'revoke_reason' => $reason,
                ])->save();
            }

            $lockedOrder->forceFill([
                'status' => OrderStatus::NeedsReview,
                'payment_status' => $paymentStatus,
                'fulfillment_status' => FulfillmentStatus::Revoked,
            ])->save();

            return $lockedOrder->refresh();
        });
    }
}
