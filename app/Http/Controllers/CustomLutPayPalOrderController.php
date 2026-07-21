<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\CheckoutConsentRequest;
use App\Models\CustomLutBuild;
use App\Models\Payment;
use App\Models\User;
use App\Models\WizardProject;
use App\Services\Checkout\CreateCustomLutCheckoutOrder;
use App\Services\PayPal\CreatePayPalOrder;
use App\Services\PayPal\PayPalApiException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CustomLutPayPalOrderController extends Controller
{
    public function __construct(
        private readonly CreateCustomLutCheckoutOrder $createCheckoutOrder,
        private readonly CreatePayPalOrder $createPayPalOrder,
    ) {}

    public function store(CheckoutConsentRequest $request, WizardProject $wizardProject, CustomLutBuild $customLutBuild): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('purchase', $customLutBuild);

        abort_unless($wizardProject->belongsToUser($user), HttpResponse::HTTP_NOT_FOUND);
        abort_unless($customLutBuild->wizard_project_id === $wizardProject->id && $customLutBuild->user_id === $user->id, HttpResponse::HTTP_NOT_FOUND);

        $order = $this->createCheckoutOrder->handle($user, $customLutBuild, $request->consentData());
        $order->loadMissing('payment');
        $payment = $order->payment;

        if (! $payment instanceof Payment) {
            abort(422, 'The local order is not ready for PayPal checkout.');
        }

        if ($payment->paypal_order_id === null) {
            try {
                $paypalOrder = $this->createPayPalOrder->handle($order);
            } catch (PayPalApiException $exception) {
                $payment->forceFill([
                    'provider_debug_id' => $exception->debugId,
                    'failure_code' => 'paypal_create_failed',
                ])->save();

                abort(503, 'PayPal checkout is temporarily unavailable.');
            }

            $paypalOrderId = is_string($paypalOrder['id'] ?? null) ? $paypalOrder['id'] : null;

            abort_if($paypalOrderId === null, HttpResponse::HTTP_SERVICE_UNAVAILABLE, 'PayPal returned an invalid order response.');

            $payment->forceFill([
                'paypal_order_id' => $paypalOrderId,
            ])->save();
        }

        return response()->json([
            'local_order_id' => $order->id,
            'local_order_number' => $order->number,
            'paypal_order_id' => $payment->paypal_order_id,
            'status' => $order->payment_status->value,
        ]);
    }
}
