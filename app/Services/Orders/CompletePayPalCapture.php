<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPal\ParsePayPalMoney;
use App\Services\PayPal\PayPalCaptureValidationResult;
use App\Services\PayPal\ValidatePayPalCapture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CompletePayPalCapture
{
    public function __construct(
        private readonly ValidatePayPalCapture $validator,
        private readonly ParsePayPalMoney $money,
        private readonly FulfillPaidOrder $fulfillPaidOrder,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public function handle(Order $order, array $response): Order
    {
        $result = DB::transaction(function () use ($order, $response): PayPalCaptureValidationResult {
            $lockedOrder = Order::query()
                ->with('payment')
                ->lockForUpdate()
                ->findOrFail($order->id);

            $payment = $lockedOrder->payment;

            if (! $payment instanceof Payment) {
                return new PayPalCaptureValidationResult(false, 'UNKNOWN', failureCode: 'missing_local_payment');
            }

            if ($payment->status === PaymentStatus::Completed && $lockedOrder->isFulfilled()) {
                return new PayPalCaptureValidationResult(true, 'COMPLETED', $payment->paypal_capture_id);
            }

            $validation = $this->validator->validate($lockedOrder, $payment, $response);

            if (! $validation->valid) {
                $this->markNeedsReview($lockedOrder, $payment, $validation);

                return $validation;
            }

            if ($validation->paypalStatus === 'COMPLETED') {
                $this->markCompleted($lockedOrder, $payment, $validation, $response);

                return $validation;
            }

            if ($validation->paypalStatus === 'PENDING') {
                $this->markPending($lockedOrder, $payment);

                return $validation;
            }

            $this->markNeedsReview($lockedOrder, $payment, new PayPalCaptureValidationResult(
                false,
                $validation->paypalStatus,
                $validation->captureId,
                $validation->capture,
                'unexpected_capture_status',
            ));

            return $validation;
        });

        if ($result->valid && $result->paypalStatus === 'COMPLETED') {
            return $this->fulfillPaidOrder->handle($order);
        }

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function markCompleted(Order $order, Payment $payment, PayPalCaptureValidationResult $result, array $response): void
    {
        $capture = $result->capture ?? [];
        $sellerBreakdown = is_array($capture['seller_receivable_breakdown'] ?? null)
            ? $capture['seller_receivable_breakdown']
            : [];

        $payment->forceFill([
            'status' => PaymentStatus::Completed,
            'paypal_capture_id' => $result->captureId,
            'payer_id' => $this->stringOrNull($response['payer']['payer_id'] ?? null),
            'payer_email' => $this->stringOrNull($response['payer']['email_address'] ?? null),
            'payer_country_code' => $this->stringOrNull($response['payer']['address']['country_code'] ?? null),
            'payee_merchant_id' => $this->payeeMerchantId($response, $capture),
            'paypal_fee_cents' => $this->optionalMoney($sellerBreakdown['paypal_fee'] ?? null),
            'net_amount_cents' => $this->optionalMoney($sellerBreakdown['net_amount'] ?? null),
            'completed_at' => now(),
            'failure_code' => null,
        ])->save();

        $order->forceFill([
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Completed,
            'paid_at' => $order->paid_at ?? now(),
        ])->save();
    }

    private function markPending(Order $order, Payment $payment): void
    {
        if ($payment->status === PaymentStatus::Completed) {
            return;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Pending,
        ])->save();

        $order->forceFill([
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
        ])->save();
    }

    private function markNeedsReview(Order $order, Payment $payment, PayPalCaptureValidationResult $result): void
    {
        if ($payment->status === PaymentStatus::Completed) {
            return;
        }

        $payment->forceFill([
            'status' => PaymentStatus::NeedsReview,
            'paypal_capture_id' => $payment->paypal_capture_id ?? $result->captureId,
            'failure_code' => Str::limit($result->failureCode ?? 'paypal_capture_mismatch', 190, ''),
        ])->save();

        $order->forceFill([
            'status' => OrderStatus::NeedsReview,
            'payment_status' => PaymentStatus::NeedsReview,
        ])->save();
    }

    private function optionalMoney(mixed $value): ?int
    {
        if (! is_array($value)) {
            return null;
        }

        try {
            return $this->money->cents($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $capture
     */
    private function payeeMerchantId(array $response, array $capture): ?string
    {
        return $this->stringOrNull($response['purchase_units'][0]['payee']['merchant_id'] ?? null)
            ?? $this->stringOrNull($capture['payee']['merchant_id'] ?? null);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
