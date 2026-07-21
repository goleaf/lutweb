<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\CompletePayPalCapture;
use App\Services\PayPal\CapturePayPalOrder;
use App\Services\PayPal\PayPalApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaptureApprovedPayPalOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly string $orderId,
    ) {
        $this->onQueue((string) config('paypal.payment_queue', 'payments'));
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('paypal-capture-'.$this->orderId))->expireAfter(300),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(CapturePayPalOrder $capture, CompletePayPalCapture $completeCapture): void
    {
        $order = Order::query()->with('payment')->find($this->orderId);

        if (! $order instanceof Order || ! $order->payment instanceof Payment) {
            return;
        }

        if ($order->payment->status === PaymentStatus::Completed || $order->payment->paypal_order_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($order->payment->id);

            if ($payment->capture_request_id === null) {
                $payment->forceFill([
                    'capture_request_id' => (string) Str::uuid(),
                ])->save();
            }
        });

        $order->refresh()->load('payment');

        try {
            $response = $capture->handle($order->payment);
        } catch (PayPalApiException $exception) {
            DB::transaction(function () use ($order, $exception): void {
                $payment = Payment::query()->lockForUpdate()->findOrFail($order->payment->id);
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                $payment->forceFill([
                    'status' => $exception->status === null ? PaymentStatus::Pending : PaymentStatus::Failed,
                    'provider_debug_id' => $exception->debugId,
                    'failure_code' => $exception->status === null ? 'capture_ambiguous' : 'capture_failed',
                ])->save();

                $lockedOrder->forceFill([
                    'status' => $exception->status === null ? OrderStatus::Processing : OrderStatus::NeedsReview,
                    'payment_status' => $exception->status === null ? PaymentStatus::Pending : PaymentStatus::Failed,
                ])->save();
            });

            if ($exception->status === null) {
                ReconcilePayPalOrder::dispatch($order->id);
            }

            return;
        }

        $completeCapture->handle($order, $response);
    }
}
