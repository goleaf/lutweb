<?php

namespace App\Http\Controllers\Account;

use App\Enums\DigitalAssetKind;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\User;
use App\Support\Catalog\EurMoney;
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
            ->where('digital_asset_kind', DigitalAssetKind::CustomLutBuild->value)
            ->latest('granted_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Account/CustomLuts/Purchased', [
            'purchases' => $entitlements->through(function (Entitlement $entitlement): array {
                $orderItem = $entitlement->orderItem;

                return [
                    'id' => $entitlement->id,
                    'name' => $orderItem?->displayName() ?? 'Custom LUT package',
                    'style_name' => $orderItem->custom_lut_style_name_snapshot ?? 'Neutral',
                    'order_number' => $entitlement->order?->number,
                    'order_url' => $entitlement->order === null ? null : route('account.orders.show', $entitlement->order),
                    'show_url' => route('account.custom-luts.purchased.show', $entitlement),
                    'download_url' => $entitlement->isActive() ? route('account.custom-luts.download', $entitlement) : null,
                    'status' => $entitlement->status->value,
                    'status_label' => $entitlement->status->label(),
                    'purchased_at' => $entitlement->granted_at->toIso8601String(),
                    'version_label' => $orderItem?->versionLabel(),
                    'parameter_hash' => substr((string) $orderItem?->custom_lut_parameters_hash, 0, 12),
                    'transform_version' => $orderItem?->custom_lut_transform_version,
                    'package_size_bytes' => $orderItem?->custom_lut_package_size_bytes,
                    'project_url' => $entitlement->wizardProject === null ? null : route('custom-lut.show', $entitlement->wizardProject),
                ];
            }),
        ]);
    }

    public function show(Request $request, Entitlement $entitlement): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('view', $entitlement);
        abort_unless($entitlement->user_id === $user->id && $entitlement->isCustomLutBuild(), HttpResponse::HTTP_NOT_FOUND);

        $entitlement->loadMissing(['orderItem', 'order.payment', 'wizardProject']);

        return Inertia::render('Account/CustomLuts/Show', [
            'purchase' => [
                'id' => $entitlement->id,
                'name' => $entitlement->orderItem?->displayName() ?? 'Custom LUT package',
                'style_name' => $entitlement->orderItem->custom_lut_style_name_snapshot ?? 'Neutral',
                'order_number' => $entitlement->order?->number,
                'order_url' => $entitlement->order === null ? null : route('account.orders.show', $entitlement->order),
                'amount' => $entitlement->order === null ? null : $entitlement->order->currency.' '.EurMoney::formatCents($entitlement->order->total_cents),
                'currency' => $entitlement->order?->currency,
                'payment_status' => $entitlement->order?->payment_status->value,
                'fulfillment_status' => $entitlement->order?->fulfillment_status->value,
                'license_version' => $entitlement->order?->license_version,
                'version_label' => $entitlement->orderItem?->versionLabel(),
                'transform_version' => $entitlement->orderItem?->custom_lut_transform_version,
                'generator_version' => $entitlement->orderItem?->custom_lut_generator_version,
                'parameter_hash' => substr((string) $entitlement->orderItem?->custom_lut_parameters_hash, 0, 12),
                'package_size_bytes' => $entitlement->orderItem?->custom_lut_package_size_bytes,
                'download_url' => $entitlement->isActive() ? route('account.custom-luts.download', $entitlement) : null,
                'project_url' => $entitlement->wizardProject === null ? null : route('custom-lut.show', $entitlement->wizardProject),
            ],
        ]);
    }
}
