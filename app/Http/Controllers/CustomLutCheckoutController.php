<?php

namespace App\Http\Controllers;

use App\Models\CustomLutBuild;
use App\Models\CustomLutCommerceSetting;
use App\Models\User;
use App\Models\WizardProject;
use App\Services\Checkout\CustomLutPurchaseEligibility;
use App\Support\Catalog\EurMoney;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CustomLutCheckoutController extends Controller
{
    public function __construct(
        private readonly CustomLutPurchaseEligibility $eligibility,
    ) {}

    public function show(Request $request, WizardProject $wizardProject, CustomLutBuild $customLutBuild): Response
    {
        $this->authorizeBuild($request, $wizardProject, $customLutBuild);

        /** @var User $user */
        $user = $request->user();
        $this->authorize('purchase', $customLutBuild);

        $result = $this->eligibility->check($customLutBuild->loadMissing(['packageFile', 'wizardProject']), $user);
        $settings = $result->settings;
        $packageFile = $result->packageFile;
        $resumeOrder = $result->order?->loadMissing('item');
        $priceCents = $settings instanceof CustomLutCommerceSetting
            ? $settings->price_cents
            : (int) ($resumeOrder->total_cents ?? 0);
        $paypalAvailable = in_array($result->state, ['eligible', 'resume'], true);

        return Inertia::render('CustomLut/Checkout', [
            'state' => $result->state,
            'message' => $result->message,
            'item' => [
                'kind' => 'custom_lut_build',
                'project_id' => $wizardProject->id,
                'build_id' => $customLutBuild->id,
                'name' => $customLutBuild->project_name_snapshot,
                'style_name' => $customLutBuild->style_name_snapshot ?? 'Neutral',
                'transform_version' => $customLutBuild->transform_version,
                'generator_version' => $customLutBuild->generator_version,
                'package_schema_version' => $customLutBuild->package_schema_version,
                'version_label' => $resumeOrder?->item?->versionLabel() ?? 'Build '.strtoupper(substr($customLutBuild->id, -8)),
                'prepared_at' => $customLutBuild->prepared_at?->toIso8601String(),
                'package_size_bytes' => $packageFile->size_bytes ?? $resumeOrder?->item?->custom_lut_package_size_bytes,
                'contents' => [
                    '17-point CUBE',
                    '33-point CUBE',
                    '65-point CUBE',
                    'License PDF',
                    'Installation Guide PDF',
                    'README',
                ],
            ],
            'pricing' => [
                'currency' => 'EUR',
                'subtotal_cents' => $priceCents,
                'tax_cents' => 0,
                'total_cents' => $priceCents,
                'subtotal' => $priceCents > 0 ? EurMoney::formatCents($priceCents) : null,
                'tax' => '0.00',
                'total' => $priceCents > 0 ? EurMoney::formatCents($priceCents) : null,
            ],
            'legal' => [
                'terms_of_sale_version' => config('legal.terms_of_sale_version'),
                'license_version' => $customLutBuild->license_version,
                'refund_policy_version' => config('legal.refund_policy_version'),
                'digital_delivery_consent_version' => config('legal.digital_delivery_consent_version'),
                'terms_url' => route('terms-of-sale'),
                'license_url' => route('license'),
                'refund_policy_url' => route('refund-policy'),
            ],
            'paypal' => [
                'client_id' => $paypalAvailable ? config('paypal.client_id') : null,
                'sdk_url' => $paypalAvailable ? config('paypal.sdk_urls.'.config('paypal.mode')) : null,
                'mode' => config('paypal.mode'),
                'currency' => 'EUR',
                'brand_name' => config('paypal.brand_name'),
            ],
            'account' => [
                'email' => $user->email,
            ],
            'links' => [
                'editor' => route('custom-lut.show', $wizardProject),
                'create_order' => route('custom-lut.checkout.paypal.orders.store', [$wizardProject, $customLutBuild]),
                'my_custom_luts' => route('account.custom-luts.purchased.index'),
            ],
        ]);
    }

    private function authorizeBuild(Request $request, WizardProject $wizardProject, CustomLutBuild $customLutBuild): void
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user instanceof User, HttpResponse::HTTP_FORBIDDEN);
        abort_unless($wizardProject->belongsToUser($user), HttpResponse::HTTP_NOT_FOUND);
        abort_unless($customLutBuild->wizard_project_id === $wizardProject->id && $customLutBuild->user_id === $user->id, HttpResponse::HTTP_NOT_FOUND);
    }
}
