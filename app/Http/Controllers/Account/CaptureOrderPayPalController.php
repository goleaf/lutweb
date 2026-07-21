<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CapturePayPalOrderRequest;
use App\Jobs\ReconcilePayPalOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\CompletePayPalCapture;
use App\Services\PayPal\CapturePayPalOrder;
use App\Services\PayPal\PayPalApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaptureOrderPayPalController extends Controller
{
    public function __invoke(
        Order $order,
        CapturePayPalOrderRequest $request,
        CapturePayPalOrder $capturePayPalOrder,
        CompletePayPalCapture $completeCapture,
    ): JsonResponse {
        $this->authorize('capture', $order);

        $order->loadMissing('payment');
        $payment = $order->payment;

        abort_unless($payment instanceof Payment && $payment->paypal_order_id !== null, 422, 'This order is not ready for capture.');

        $callbackOrderId = $request->validated('paypal_order_id');

        abort_if(is_string($callbackOrderId) && $callbackOrderId !== $payment->paypal_order_id, 422, 'The PayPal order does not match this order.');

        if ($payment->status === PaymentStatus::Completed && $order->isFulfilled()) {
            return response()->json($this->response($order->refresh()));
        }

        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->capture_request_id === null) {
                $locked->forceFill([
                    'capture_request_id' => (string) Str::uuid(),
                ])->save();
            }
        });

        $order->refresh()->load('payment');

        try {
            $response = $capturePayPalOrder->handle($order->payment);
        } catch (PayPalApiException $exception) {
            DB::transaction(function () use ($order, $exception): void {
                $payment = Payment::query()->lockForUpdate()->findOrFail($order->payment->id);
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                $ambiguous = $exception->status === null;

                $payment->forceFill([
                    'status' => $ambiguous ? PaymentStatus::Pending : PaymentStatus::Failed,
                    'provider_debug_id' => $exception->debugId,
                    'failure_code' => $ambiguous ? 'capture_ambiguous' : 'capture_failed',
                ])->save();

                $lockedOrder->forceFill([
                    'status' => $ambiguous ? OrderStatus::Processing : OrderStatus::NeedsReview,
                    'payment_status' => $ambiguous ? PaymentStatus::Pending : PaymentStatus::Failed,
                ])->save();
            });

            if ($exception->status === null) {
                ReconcilePayPalOrder::dispatch($order->id);
            }

            return response()->json($this->response($order->refresh()), $exception->status === null ? 202 : 422);
        }

        $completed = $completeCapture->handle($order, $response);

        return response()->json($this->response($completed));
    }

    /**
     * @return array<string, mixed>
     */
    private function response(Order $order): array
    {
        return [
            'local_order_id' => $order->id,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'order_url' => route('account.orders.show', $order),
        ];
    }
}
