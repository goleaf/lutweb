<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Queries\Storefront\ProductCatalogQuery;
use App\Services\Checkout\ProductPurchaseEligibility;
use App\Support\Catalog\EurMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ShowCheckoutController extends Controller
{
    public function __invoke(string $slug, Request $request, ProductCatalogQuery $catalog, ProductPurchaseEligibility $eligibility): Response
    {
        $product = $catalog->findPublishedBySlug($slug);
        $result = $eligibility->check($product, $request->user());
        $package = $result->package;

        return Inertia::render('Checkout/Show', [
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
                'type' => $product->type->value,
                'type_label' => $product->type->label(),
                'url' => route('shop.show', $product->slug),
                'cover' => $product->coverMedia ? [
                    'url' => Storage::disk('public')->url($product->coverMedia->path),
                    'alt_text' => $product->coverMedia->alt_text,
                ] : null,
                'version' => $package?->version->version ?? $product->currentVersion?->version,
                'package_contents' => ['ZIP package'],
            ],
            'purchase' => [
                'action' => $result->action,
                'message' => $result->message,
                'create_order_url' => $result->action === 'buy' ? route('checkout.paypal.orders.store', $product->slug) : null,
                'claim_url' => $result->action === 'claim' ? route('checkout.free.claim', $product->slug) : null,
                'owned_url' => route('account.luts.index'),
            ],
            'pricing' => [
                'currency' => 'EUR',
                'subtotal_cents' => $product->price_cents,
                'tax_cents' => 0,
                'total_cents' => $product->price_cents,
                'subtotal' => 'EUR '.EurMoney::formatCents($product->price_cents),
                'tax' => 'EUR 0.00',
                'total' => 'EUR '.EurMoney::formatCents($product->price_cents),
            ],
            'legal' => [
                'terms_of_sale_url' => route('terms-of-sale'),
                'license_url' => route('license'),
                'refund_policy_url' => route('refund-policy'),
                'terms_of_sale_version' => config('legal.terms_of_sale_version'),
                'license_version' => config('legal.license_version'),
                'refund_policy_version' => config('legal.refund_policy_version'),
                'digital_delivery_consent_version' => config('legal.digital_delivery_consent_version'),
            ],
            'paypal' => [
                'client_id' => $result->action === 'buy' ? config('paypal.client_id') : null,
                'sdk_url' => $result->action === 'buy' ? config('paypal.sdk_urls.'.config('paypal.mode')) : null,
                'mode' => config('paypal.mode'),
                'currency' => 'EUR',
                'brand_name' => config('paypal.brand_name'),
            ],
            'account' => [
                'email' => $request->user()?->email,
            ],
        ]);
    }
}
