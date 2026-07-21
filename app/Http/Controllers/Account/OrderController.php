<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OrderController extends Controller
{
    public function show(Request $request, Order $order): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($order->belongsToUser($user), HttpResponse::HTTP_NOT_FOUND);

        $order->loadMissing(['item.entitlement', 'payment']);

        return Inertia::render('Account/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'fulfillment_status' => $order->fulfillment_status->value,
                'currency' => $order->currency,
                'subtotal_cents' => $order->subtotal_cents,
                'tax_cents' => $order->tax_cents,
                'total_cents' => $order->total_cents,
                'license_version' => $order->license_version,
                'item' => $order->item === null ? null : [
                    'kind' => $order->item->digital_asset_kind->value,
                    'kind_label' => $order->item->digital_asset_kind->label(),
                    'name' => $order->item->displayName(),
                    'version' => $order->item->versionLabel(),
                ],
                'capture_url' => route('account.orders.paypal.capture', $order),
            ],
        ]);
    }
}
