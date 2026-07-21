<?php

namespace App\Http\Controllers\Account;

use App\Enums\DigitalAssetKind;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CustomLutPurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $entitlements = Entitlement::query()
            ->with(['orderItem', 'order', 'wizardProject'])
            ->where('user_id', $user->id)
            ->where('digital_asset_kind', DigitalAssetKind::CustomLutBuild)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Account/CustomLuts/Purchased', [
            'purchases' => $entitlements->through(fn (Entitlement $entitlement): array => [
                'id' => $entitlement->id,
                'name' => $entitlement->orderItem->displayName(),
                'style_name' => $entitlement->orderItem->custom_lut_style_name_snapshot ?? 'Neutral',
                'order_number' => $entitlement->order->number,
                'order_url' => route('account.orders.show', $entitlement->order),
                'download_url' => $entitlement->isActive() ? route('account.luts.download', $entitlement) : null,
                'status' => $entitlement->status->value,
                'purchased_at' => $entitlement->granted_at->toIso8601String(),
                'version_label' => $entitlement->orderItem->versionLabel(),
                'parameter_hash' => substr((string) $entitlement->orderItem->custom_lut_parameters_hash, 0, 12),
                'package_size_bytes' => $entitlement->orderItem->custom_lut_package_size_bytes,
                'project_url' => $entitlement->wizard_project_id === null ? null : route('custom-lut.show', $entitlement->wizard_project_id),
            ]),
        ]);
    }

    public function show(Request $request, Entitlement $entitlement): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($entitlement->user_id === $user->id && $entitlement->isCustomLutBuild(), HttpResponse::HTTP_NOT_FOUND);

        $entitlement->loadMissing(['orderItem', 'order.payment']);

        return Inertia::render('Account/CustomLuts/Show', [
            'purchase' => [
                'id' => $entitlement->id,
                'name' => $entitlement->orderItem->displayName(),
                'order_number' => $entitlement->order->number,
                'amount' => $entitlement->order->total_cents,
                'currency' => $entitlement->order->currency,
                'payment_status' => $entitlement->order->payment_status->value,
                'fulfillment_status' => $entitlement->order->fulfillment_status->value,
                'license_version' => $entitlement->order->license_version,
                'download_url' => $entitlement->isActive() ? route('account.luts.download', $entitlement) : null,
            ],
        ]);
    }
}
