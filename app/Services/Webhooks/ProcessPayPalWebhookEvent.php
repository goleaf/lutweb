<?php

namespace App\Services\Webhooks;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayPalWebhookProcessingStatus;
use App\Enums\PayPalWebhookVerificationStatus;
use App\Jobs\CaptureApprovedPayPalOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayPalWebhookEvent;
use App\Services\Orders\FulfillPaidOrder;
use App\Services\Orders\RevokeOrderEntitlement;
use App\Services\PayPal\ParsePayPalMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ProcessPayPalWebhookEvent
{
    public function __construct(
        private readonly FulfillPaidOrder $fulfillPaidOrder,
        private readonly RevokeOrderEntitlement $revokeOrderEntitlement,
        private readonly ParsePayPalMoney $money,
    ) {}

    public function handle(PayPalWebhookEvent $event): void
    {
        $locked = $this->beginProcessing($event);

        if ($locked === null) {
            return;
        }

        try {
            $payload = $this->payload($locked);
            $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];

            match ($locked->event_type) {
                'CHECKOUT.ORDER.APPROVED' => $this->handleApproved($resource),
                'CHECKOUT.PAYMENT-APPROVAL.REVERSED' => $this->handleApprovalReversed($resource),
                'PAYMENT.CAPTURE.PENDING' => $this->handleCapturePending($resource),
                'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($resource),
                'PAYMENT.CAPTURE.DECLINED',
                'PAYMENT.CAPTURE.DENIED' => $this->handleCaptureDeclined($resource),
                'PAYMENT.CAPTURE.REVERSED' => $this->handleCaptureReversed($resource),
                'PAYMENT.CAPTURE.REFUNDED' => $this->handleCaptureRefunded($resource),
                'CUSTOMER.DISPUTE.CREATED',
                'CUSTOMER.DISPUTE.UPDATED',
                'CUSTOMER.DISPUTE.RESOLVED' => $this->handleDispute($resource),
                default => null,
            };

            if (! in_array($locked->event_type, (array) config('paypal.recommended_webhook_events', []), true)) {
                $this->markFinished($locked, PayPalWebhookProcessingStatus::Ignored);

                return;
            }

            if ($locked->processing_status === PayPalWebhookProcessingStatus::Processing) {
                $this->markFinished($locked, PayPalWebhookProcessingStatus::Processed);
            }
        } catch (RuntimeException $exception) {
            $this->markFailed($locked, $exception->getMessage());

            throw $exception;
        }
    }

    private function beginProcessing(PayPalWebhookEvent $event): ?PayPalWebhookEvent
    {
        return DB::transaction(function () use ($event): ?PayPalWebhookEvent {
            $locked = PayPalWebhookEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->verification_status !== PayPalWebhookVerificationStatus::Verified) {
                return null;
            }

            if (in_array($locked->processing_status, [
                PayPalWebhookProcessingStatus::Processed,
                PayPalWebhookProcessingStatus::Ignored,
            ], true)) {
                return null;
            }

            $locked->forceFill([
                'processing_status' => PayPalWebhookProcessingStatus::Processing,
                'processing_attempts' => $locked->processing_attempts + 1,
            ])->save();

            return $locked;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PayPalWebhookEvent $event): array
    {
        if (! is_string($event->encrypted_payload) || $event->encrypted_payload === '') {
            throw new RuntimeException('payload_missing');
        }

        $payload = json_decode($event->encrypted_payload, true);

        if (! is_array($payload)) {
            throw new RuntimeException('payload_invalid');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleApproved(array $resource): void
    {
        $paypalOrderId = $this->stringOrNull($resource['id'] ?? null);

        if ($paypalOrderId === null) {
            throw new RuntimeException('paypal_order_missing');
        }

        $payment = Payment::query()->with('order')->where('paypal_order_id', $paypalOrderId)->first();

        if (! $payment instanceof Payment || ! $payment->order instanceof Order) {
            throw new RuntimeException('local_payment_missing');
        }

        if (! in_array($payment->status, [PaymentStatus::Completed, PaymentStatus::Reversed, PaymentStatus::Refunded], true)) {
            DB::transaction(function () use ($payment): void {
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

                if ($lockedPayment->status === PaymentStatus::Completed) {
                    return;
                }

                $lockedPayment->forceFill([
                    'status' => PaymentStatus::Approved,
                    'approved_at' => $lockedPayment->approved_at ?? now(),
                ])->save();

                $order->forceFill([
                    'status' => OrderStatus::Processing,
                    'payment_status' => PaymentStatus::Approved,
                ])->save();
            });

            CaptureApprovedPayPalOrder::dispatch($payment->order_id);
        }
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleApprovalReversed(array $resource): void
    {
        $paypalOrderId = $this->stringOrNull($resource['id'] ?? null);

        if ($paypalOrderId === null) {
            return;
        }

        $payment = Payment::query()->with('order')->where('paypal_order_id', $paypalOrderId)->first();

        if (! $payment instanceof Payment || ! $payment->order instanceof Order || $payment->status === PaymentStatus::Completed) {
            return;
        }

        DB::transaction(function () use ($payment): void {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            if ($lockedPayment->status === PaymentStatus::Completed) {
                return;
            }

            $lockedPayment->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_code' => 'payment_approval_reversed',
            ])->save();

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'payment_status' => PaymentStatus::Failed,
                'cancelled_at' => $order->cancelled_at ?? now(),
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleCapturePending(array $resource): void
    {
        $payment = $this->paymentForCaptureResource($resource);

        DB::transaction(function () use ($payment): void {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            if ($lockedPayment->status === PaymentStatus::Completed) {
                return;
            }

            $lockedPayment->forceFill(['status' => PaymentStatus::Pending])->save();
            $order->forceFill([
                'status' => OrderStatus::Processing,
                'payment_status' => PaymentStatus::Pending,
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleCaptureCompleted(array $resource): void
    {
        $payment = $this->paymentForCaptureResource($resource);

        DB::transaction(function () use ($payment, $resource): void {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            if (in_array($lockedPayment->status, [PaymentStatus::Reversed, PaymentStatus::Refunded], true)) {
                return;
            }

            if (! $this->captureMatchesPayment($lockedPayment, $order, $resource)) {
                $lockedPayment->forceFill([
                    'status' => PaymentStatus::NeedsReview,
                    'failure_code' => 'webhook_capture_mismatch',
                ])->save();

                $order->forceFill([
                    'status' => OrderStatus::NeedsReview,
                    'payment_status' => PaymentStatus::NeedsReview,
                ])->save();

                return;
            }

            $lockedPayment->forceFill([
                'status' => PaymentStatus::Completed,
                'paypal_capture_id' => $this->stringOrNull($resource['id'] ?? null),
                'payee_merchant_id' => $this->stringOrNull($resource['payee']['merchant_id'] ?? null),
                'paypal_fee_cents' => $this->optionalMoney($resource['seller_receivable_breakdown']['paypal_fee'] ?? null),
                'net_amount_cents' => $this->optionalMoney($resource['seller_receivable_breakdown']['net_amount'] ?? null),
                'completed_at' => $lockedPayment->completed_at ?? now(),
                'failure_code' => null,
            ])->save();

            $order->forceFill([
                'status' => OrderStatus::Processing,
                'payment_status' => PaymentStatus::Completed,
                'paid_at' => $order->paid_at ?? now(),
            ])->save();
        });

        $this->fulfillPaidOrder->handle($payment->order);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleCaptureDeclined(array $resource): void
    {
        $payment = $this->paymentForCaptureResource($resource);

        DB::transaction(function () use ($payment, $resource): void {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            if ($lockedPayment->status === PaymentStatus::Completed) {
                return;
            }

            $lockedPayment->forceFill([
                'status' => PaymentStatus::Declined,
                'paypal_capture_id' => $lockedPayment->paypal_capture_id ?? $this->stringOrNull($resource['id'] ?? null),
                'failure_code' => 'capture_declined',
            ])->save();

            $order->forceFill([
                'status' => OrderStatus::NeedsReview,
                'payment_status' => PaymentStatus::Declined,
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleCaptureReversed(array $resource): void
    {
        $payment = $this->paymentForCaptureResource($resource);

        $this->revokeOrderEntitlement->handle($payment->order, PaymentStatus::Reversed, 'External PayPal reversal.');
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleCaptureRefunded(array $resource): void
    {
        $captureId = $this->stringOrNull($resource['supplementary_data']['related_ids']['capture_id'] ?? null)
            ?? $this->stringOrNull($resource['links'][0]['href'] ?? null);

        $payment = $captureId !== null
            ? Payment::query()->with('order')->where('paypal_capture_id', $captureId)->first()
            : null;

        if (! $payment instanceof Payment || ! $payment->order instanceof Order) {
            throw new RuntimeException('local_payment_missing');
        }

        $refunded = $this->optionalMoney($resource['amount'] ?? null) ?? 0;

        DB::transaction(function () use ($payment, $refunded): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $locked->forceFill([
                'refunded_amount_cents' => max($locked->refunded_amount_cents, $refunded),
            ])->save();
        });

        $status = $refunded >= $payment->amount_cents ? PaymentStatus::Refunded : PaymentStatus::NeedsReview;

        $this->revokeOrderEntitlement->handle($payment->order, $status, 'External PayPal refund.');
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleDispute(array $resource): void
    {
        $captureId = collect($resource['disputed_transactions'] ?? [])
            ->pluck('seller_transaction_id')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->first();

        if (! is_string($captureId)) {
            return;
        }

        $payment = Payment::query()->with('order')->where('paypal_capture_id', $captureId)->first();

        if (! $payment instanceof Payment || ! $payment->order instanceof Order) {
            return;
        }

        $this->revokeOrderEntitlement->handle($payment->order, PaymentStatus::NeedsReview, 'PayPal dispute requires administrator review.');
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function paymentForCaptureResource(array $resource): Payment
    {
        $captureId = $this->stringOrNull($resource['id'] ?? null);
        $paypalOrderId = $this->stringOrNull($resource['supplementary_data']['related_ids']['order_id'] ?? null);

        $payment = Payment::query()
            ->with('order')
            ->when($captureId !== null, fn ($query) => $query->where('paypal_capture_id', $captureId))
            ->first();

        if (! $payment instanceof Payment && $paypalOrderId !== null) {
            $payment = Payment::query()->with('order')->where('paypal_order_id', $paypalOrderId)->first();
        }

        if (! $payment instanceof Payment || ! $payment->order instanceof Order) {
            throw new RuntimeException('local_payment_missing');
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function captureMatchesPayment(Payment $payment, Order $order, array $resource): bool
    {
        $captureId = $this->stringOrNull($resource['id'] ?? null);
        $paypalOrderId = $this->stringOrNull($resource['supplementary_data']['related_ids']['order_id'] ?? null);
        $merchantId = config('paypal.merchant_id');
        $payeeMerchantId = $this->stringOrNull($resource['payee']['merchant_id'] ?? null);

        if ($captureId === null || ($payment->paypal_capture_id !== null && $payment->paypal_capture_id !== $captureId)) {
            return false;
        }

        if ($paypalOrderId === null || $payment->paypal_order_id !== $paypalOrderId) {
            return false;
        }

        if (filled($merchantId) && $payeeMerchantId !== $merchantId) {
            return false;
        }

        return $this->optionalMoney($resource['amount'] ?? null) === $order->total_cents;
    }

    private function markFinished(PayPalWebhookEvent $event, PayPalWebhookProcessingStatus $status): void
    {
        DB::transaction(function () use ($event, $status): void {
            $locked = PayPalWebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
            $locked->forceFill([
                'processing_status' => $status,
                'encrypted_payload' => null,
                'processed_at' => now(),
                'payload_purged_at' => now(),
                'failure_code' => null,
            ])->save();
        });
    }

    private function markFailed(PayPalWebhookEvent $event, string $failureCode): void
    {
        DB::transaction(function () use ($event, $failureCode): void {
            $locked = PayPalWebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
            $locked->forceFill([
                'processing_status' => PayPalWebhookProcessingStatus::Failed,
                'failure_code' => Str::limit($failureCode, 190, ''),
            ])->save();
        });
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

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
