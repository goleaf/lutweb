<?php

namespace App\Services\Orders;

use App\Enums\DigitalAssetKind;
use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use LogicException;

class GrantOrderEntitlement
{
    public function handle(Order $order): Entitlement
    {
        return DB::transaction(function () use ($order): Entitlement {
            $lockedOrder = Order::query()
                ->with('item')
                ->lockForUpdate()
                ->findOrFail($order->id);

            $item = $lockedOrder->item;

            if (! $item instanceof OrderItem) {
                throw new LogicException('Cannot grant an entitlement for an order without an item.');
            }

            $existing = $item->digital_asset_kind === DigitalAssetKind::CustomLutBuild && $item->custom_lut_build_id !== null
                ? Entitlement::query()
                    ->where('user_id', $lockedOrder->user_id)
                    ->where('custom_lut_build_id', $item->custom_lut_build_id)
                    ->lockForUpdate()
                    ->first()
                : Entitlement::query()
                    ->where('order_item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

            if ($existing instanceof Entitlement) {
                return $existing;
            }

            return Entitlement::query()->create([
                'user_id' => $lockedOrder->user_id,
                'digital_asset_kind' => $item->digital_asset_kind,
                'order_id' => $lockedOrder->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_version_id' => $item->product_version_id,
                'product_file_id' => $item->product_file_id,
                'wizard_project_id' => $item->wizard_project_id,
                'custom_lut_build_id' => $item->custom_lut_build_id,
                'custom_lut_build_file_id' => $item->custom_lut_build_file_id,
                'status' => EntitlementStatus::Active,
                'granted_at' => now(),
            ]);
        });
    }
}
