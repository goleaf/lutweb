<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPal\FetchPayPalOrder;
use Illuminate\Support\Facades\DB;

class ReconcilePayPalOrder
{
    public function __construct(
        private readonly FetchPayPalOrder $fetchPayPalOrder,
        private readonly CompletePayPalCapture $completeCapture,
    ) {}

    public function handle(Order $order): Order
    {
        $order->loadMissing('payment');
        $payment = $order->payment;

        if (! $payment instanceof Payment || $payment->paypal_order_id === null) {
            return $order;
        }

        $response = $this->fetchPayPalOrder->handle($payment->paypal_order_id);

        if ($this->hasCapture($response)) {
            return $this->completeCapture->handle($order, $response);
        }

        $status = is_string($response['status'] ?? null) ? $response['status'] : 'UNKNOWN';

        DB::transaction(function () use ($order, $payment, $status): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (in_array($lockedPayment->status, [PaymentStatus::Completed, PaymentStatus::Reversed, PaymentStatus::Refunded], true)) {
                return;
            }

            if ($status === 'APPROVED') {
                $lockedPayment->forceFill([
                    'status' => PaymentStatus::Approved,
                    'approved_at' => $lockedPayment->approved_at ?? now(),
                ])->save();

                $lockedOrder->forceFill([
                    'status' => OrderStatus::Processing,
                    'payment_status' => PaymentStatus::Approved,
                ])->save();

                return;
            }

            if ($status === 'PENDING') {
                $lockedPayment->forceFill(['status' => PaymentStatus::Pending])->save();
                $lockedOrder->forceFill([
                    'status' => OrderStatus::Processing,
                    'payment_status' => PaymentStatus::Pending,
                ])->save();

                return;
            }

            if (in_array($status, ['VOIDED', 'DECLINED', 'DENIED'], true)) {
                $lockedPayment->forceFill(['status' => PaymentStatus::Declined])->save();
                $lockedOrder->forceFill([
                    'status' => OrderStatus::Cancelled,
                    'payment_status' => PaymentStatus::Declined,
                    'cancelled_at' => $lockedOrder->cancelled_at ?? now(),
                ])->save();
            }
        });

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function hasCapture(array $response): bool
    {
        return is_array($response['purchase_units'][0]['payments']['captures'][0] ?? null);
    }
}
