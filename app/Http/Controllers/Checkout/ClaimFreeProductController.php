<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutConsentRequest;
use App\Queries\Storefront\ProductCatalogQuery;
use App\Services\Checkout\CreateCheckoutOrder;
use App\Services\Checkout\ProductPurchaseEligibility;
use App\Services\Orders\FulfillFreeOrder;
use Illuminate\Http\RedirectResponse;

class ClaimFreeProductController extends Controller
{
    public function __invoke(
        string $slug,
        CheckoutConsentRequest $request,
        ProductCatalogQuery $catalog,
        ProductPurchaseEligibility $eligibility,
        CreateCheckoutOrder $orders,
        FulfillFreeOrder $fulfillFreeOrder,
    ): RedirectResponse {
        $product = $catalog->findPublishedBySlug($slug);
        $result = $eligibility->check($product, $request->user());

        if ($result->action === 'owned') {
            return redirect()->route('account.luts.index');
        }

        abort_unless($result->action === 'claim' && $result->package !== null, 422, $result->message ?? 'This free LUT is not available.');

        $order = $orders->free($request->user(), $product, $result->package, $request->consentData());
        $fulfillFreeOrder->handle($order);

        return redirect()->route('account.luts.index');
    }
}
