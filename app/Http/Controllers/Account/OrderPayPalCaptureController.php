<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CapturePayPalOrderRequest;
use App\Models\Order;
use App\Services\Checkout\FulfillPaidOrder;
use App\Services\PayPal\CapturePayPalOrder;
use App\Services\PayPal\ValidatePayPalCapture;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OrderPayPalCaptureController extends Controller
{
    public function __construct(
        private readonly CapturePayPalOrder $capturePayPalOrder,
        private readonly ValidatePayPalCapture $validatePayPalCapture,
        private readonly FulfillPaidOrder $fulfillPaidOrder,
    ) {}

    public function store(CapturePayPalOrderRequest $request, Order $order): JsonResponse
    {
        $order->loadMissing(['payment', 'item']);
        $payment = $order->payment;

        abort_if($payment === null || $payment->paypal_order_id === null, HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

        $submittedPayPalOrderId = $request->validated('paypal_order_id');

        abort_if(is_string($submittedPayPalOrderId) && $submittedPayPalOrderId !== $payment->paypal_order_id, HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

        if ($payment->status === PaymentStatus::Completed) {
            $fulfilled = $this->fulfillPaidOrder->handle($order);

            return response()->json([
                'status' => $fulfilled->payment_status->value,
                'order_status' => $fulfilled->status->value,
                'fulfillment_status' => $fulfilled->fulfillment_status->value,
            ]);
        }

        if ($payment->capture_request_id === null) {
            $payment->forceFill(['capture_request_id' => (string) Str::uuid()])->save();
        }

        $response = $this->capturePayPalOrder->handle($payment);
        $result = $this->validatePayPalCapture->validate($order, $payment->refresh(), $response);

        if (! $result->valid || $result->paypalStatus !== 'COMPLETED') {
            $payment->forceFill([
                'status' => match ($result->paypalStatus) {
                    'APPROVED' => PaymentStatus::Approved,
                    'PENDING' => PaymentStatus::Pending,
                    default => PaymentStatus::NeedsReview,
                },
                'failure_code' => $result->failureCode,
            ])->save();

            $order->forceFill([
                'status' => OrderStatus::NeedsReview,
                'payment_status' => $payment->status,
            ])->save();

            return response()->json([
                'status' => $payment->status->value,
                'order_status' => $order->status->value,
                'fulfillment_status' => $order->fulfillment_status->value,
            ], HttpResponse::HTTP_ACCEPTED);
        }

        $capture = $result->capture ?? [];

        $payment->forceFill([
            'status' => PaymentStatus::Completed,
            'paypal_capture_id' => $result->captureId,
            'payer_id' => data_get($response, 'payer.payer_id'),
            'payer_email' => data_get($response, 'payer.email_address'),
            'payer_country_code' => data_get($response, 'payer.address.country_code'),
            'payee_merchant_id' => data_get($response, 'purchase_units.0.payee.merchant_id'),
            'provider_debug_id' => data_get($capture, 'processor_response.debug_id'),
            'completed_at' => now(),
            'failure_code' => null,
        ])->save();

        $order->forceFill([
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ])->save();

        $fulfilled = $this->fulfillPaidOrder->handle($order->refresh());

        return response()->json([
            'status' => $fulfilled->payment_status->value,
            'order_status' => $fulfilled->status->value,
            'fulfillment_status' => $fulfilled->fulfillment_status->value,
        ]);
    }
}
