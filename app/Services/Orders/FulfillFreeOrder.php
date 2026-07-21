<?php

namespace App\Services\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Notifications\LutReadyForDownload;
use Illuminate\Support\Facades\DB;

class FulfillFreeOrder
{
    public function __construct(
        private readonly GrantOrderEntitlement $grantEntitlement,
    ) {}

    public function handle(Order $order): Order
    {
        $alreadyFulfilled = $order->isFulfilled() && $order->entitlement()->exists();

        $fulfilled = DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()
                ->with(['entitlement', 'user'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->payment_status !== PaymentStatus::NotRequired) {
                return $lockedOrder;
            }

            $now = now();

            $lockedOrder->forceFill([
                'status' => OrderStatus::Completed,
                'fulfillment_status' => FulfillmentStatus::Ready,
                'paid_at' => $lockedOrder->paid_at ?? $now,
                'fulfilled_at' => $lockedOrder->fulfilled_at ?? $now,
            ])->save();

            $this->grantEntitlement->handle($lockedOrder);

            return $lockedOrder->refresh()->load(['entitlement', 'user']);
        });

        if (! $alreadyFulfilled && $fulfilled->user !== null) {
            $fulfilled->user->notify(new LutReadyForDownload($fulfilled));
        }

        return $fulfilled;
    }
}
