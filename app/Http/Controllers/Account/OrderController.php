<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Catalog\EurMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->with(['item'])
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Account/Orders/Index', [
            'orders' => [
                'data' => $orders->getCollection()->map(fn (Order $order): array => $this->summary($order))->values(),
                'meta' => Arr::except($orders->toArray(), ['data']),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->loadMissing(['item', 'payment', 'entitlement']);

        return Inertia::render('Account/Orders/Show', [
            'order' => [
                ...$this->summary($order),
                'subtotal' => 'EUR '.EurMoney::formatCents($order->subtotal_cents),
                'tax' => 'EUR '.EurMoney::formatCents($order->tax_cents),
                'total' => 'EUR '.EurMoney::formatCents($order->total_cents),
                'terms_of_sale_version' => $order->terms_of_sale_version,
                'license_version' => $order->license_version,
                'refund_policy_version' => $order->refund_policy_version,
                'digital_delivery_consent_version' => $order->digital_delivery_consent_version,
                'paid_at' => $order->paid_at?->toISOString(),
                'fulfilled_at' => $order->fulfilled_at?->toISOString(),
                'paypal_reference' => $order->payment?->paypal_capture_id
                    ? Str::mask($order->payment->paypal_capture_id, '*', 4, max(strlen($order->payment->paypal_capture_id) - 8, 0))
                    : null,
                'item' => $this->item($order),
                'download_url' => $this->downloadUrl($order),
                'capture_url' => route('account.orders.paypal.capture', $order),
                'polling' => in_array($order->payment_status, [PaymentStatus::Created, PaymentStatus::Approved, PaymentStatus::Pending], true),
            ],
        ]);
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Failed,
            'cancelled_at' => now(),
        ])->save();

        return redirect()->route('account.orders.show', $order);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'kind' => $order->item?->digital_asset_kind->value,
            'kind_label' => $order->item?->digital_asset_kind->label() ?? 'Digital product',
            'name' => $order->item?->displayName() ?? 'Purchased LUT',
            'version' => $order->item?->versionLabel(),
            'product_name' => $order->item?->displayName(),
            'product_type' => $order->item?->product_type,
            'product_version' => $order->item?->versionLabel(),
            'created_at' => $order->created_at?->toISOString(),
            'amount' => 'EUR '.EurMoney::formatCents($order->total_cents),
            'currency' => $order->currency,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_status' => $order->payment_status->value,
            'payment_status_label' => $order->payment_status->label(),
            'fulfillment_status' => $order->fulfillment_status->value,
            'fulfillment_status_label' => $order->fulfillment_status->label(),
            'url' => route('account.orders.show', $order),
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function item(Order $order): ?array
    {
        $item = $order->item;

        if ($item === null) {
            return null;
        }

        return [
            'kind' => $item->digital_asset_kind->value,
            'kind_label' => $item->digital_asset_kind->label(),
            'name' => $item->displayName(),
            'version' => $item->versionLabel(),
        ];
    }

    private function downloadUrl(Order $order): ?string
    {
        $entitlement = $order->entitlement;

        if ($entitlement?->isActive() !== true) {
            return null;
        }

        if ($order->item?->isCustomLutBuild()) {
            return route('account.custom-luts.download', $entitlement);
        }

        return route('account.luts.download', $entitlement);
    }
}
