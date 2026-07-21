<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutConsentRequest;
use App\Models\Payment;
use App\Queries\Storefront\ProductCatalogQuery;
use App\Services\Checkout\CreateCheckoutOrder;
use App\Services\Checkout\ProductPurchaseEligibility;
use App\Services\PayPal\CreatePayPalOrder;
use App\Services\PayPal\PayPalApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StorePayPalOrderController extends Controller
{
    public function __invoke(
        string $slug,
        CheckoutConsentRequest $request,
        ProductCatalogQuery $catalog,
        ProductPurchaseEligibility $eligibility,
        CreateCheckoutOrder $orders,
        CreatePayPalOrder $createPayPalOrder,
    ): JsonResponse {
        $product = $catalog->findPublishedBySlug($slug);
        $result = $eligibility->check($product, $request->user());

        abort_unless($result->action === 'buy' && $result->package !== null, 422, $result->message ?? 'This product is not available for checkout.');

        $order = $orders->paid($request->user(), $product, $result->package, $request->consentData());
        $order->loadMissing('payment');

        if (! $order->payment instanceof Payment) {
            abort(422, 'The local order is not ready for PayPal checkout.');
        }

        if ($order->payment->paypal_order_id === null) {
            try {
                $paypalOrder = $createPayPalOrder->handle($order);
            } catch (PayPalApiException $exception) {
                $order->payment->forceFill([
                    'provider_debug_id' => $exception->debugId,
                    'failure_code' => 'paypal_create_failed',
                ])->save();

                abort(503, 'PayPal checkout is temporarily unavailable.');
            }

            $paypalOrderId = is_string($paypalOrder['id'] ?? null) ? $paypalOrder['id'] : null;

            abort_unless($paypalOrderId !== null, 503, 'PayPal returned an invalid order response.');

            DB::transaction(function () use ($order, $paypalOrderId): void {
                $payment = Payment::query()->lockForUpdate()->findOrFail($order->payment->id);
                $payment->forceFill([
                    'paypal_order_id' => $paypalOrderId,
                ])->save();
            });

            $order->refresh()->load('payment');
        }

        return response()->json([
            'local_order_id' => $order->id,
            'paypal_order_id' => $order->payment->paypal_order_id,
            'status' => $order->payment_status->value,
        ]);
    }
}
