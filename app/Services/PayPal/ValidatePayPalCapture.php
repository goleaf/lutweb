<?php

namespace App\Services\PayPal;

use App\Models\Order;
use App\Models\Payment;
use InvalidArgumentException;

class ValidatePayPalCapture
{
    public function __construct(
        private readonly ParsePayPalMoney $money,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public function validate(Order $order, Payment $payment, array $response): PayPalCaptureValidationResult
    {
        $paypalStatus = is_string($response['status'] ?? null) ? $response['status'] : 'UNKNOWN';

        if (($response['id'] ?? null) !== $payment->paypal_order_id) {
            return new PayPalCaptureValidationResult(false, $paypalStatus, failureCode: 'paypal_order_mismatch');
        }

        $purchaseUnit = $response['purchase_units'][0] ?? null;

        if (! is_array($purchaseUnit)) {
            return new PayPalCaptureValidationResult(false, $paypalStatus, failureCode: 'missing_purchase_unit');
        }

        if (($purchaseUnit['custom_id'] ?? null) !== $order->id || ($purchaseUnit['invoice_id'] ?? null) !== $order->number) {
            return new PayPalCaptureValidationResult(false, $paypalStatus, failureCode: 'local_order_reference_mismatch');
        }

        $capture = $purchaseUnit['payments']['captures'][0] ?? null;

        if (! is_array($capture)) {
            return new PayPalCaptureValidationResult(false, $paypalStatus, failureCode: 'missing_capture');
        }

        $captureStatus = is_string($capture['status'] ?? null) ? $capture['status'] : $paypalStatus;
        $captureId = is_string($capture['id'] ?? null) ? $capture['id'] : null;

        try {
            $capturedCents = $this->money->cents(is_array($capture['amount'] ?? null) ? $capture['amount'] : []);
        } catch (InvalidArgumentException) {
            return new PayPalCaptureValidationResult(false, $captureStatus, $captureId, $capture, 'capture_amount_invalid');
        }

        if ($capturedCents !== $order->total_cents) {
            return new PayPalCaptureValidationResult(false, $captureStatus, $captureId, $capture, 'capture_amount_mismatch');
        }

        $merchantId = config('paypal.merchant_id');
        $payeeMerchantId = $purchaseUnit['payee']['merchant_id'] ?? $capture['payee']['merchant_id'] ?? null;

        if (filled($merchantId) && $payeeMerchantId !== $merchantId) {
            return new PayPalCaptureValidationResult(false, $captureStatus, $captureId, $capture, 'merchant_mismatch');
        }

        $configuredPayeeEmail = config('paypal.payee_email');
        $responsePayeeEmail = $purchaseUnit['payee']['email_address'] ?? $capture['payee']['email_address'] ?? null;

        if (is_string($configuredPayeeEmail)
            && $configuredPayeeEmail !== ''
            && (! is_string($responsePayeeEmail) || strcasecmp($responsePayeeEmail, $configuredPayeeEmail) !== 0)) {
            return new PayPalCaptureValidationResult(false, $captureStatus, $captureId, $capture, 'payee_email_mismatch');
        }

        return new PayPalCaptureValidationResult(true, $captureStatus, $captureId, $capture);
    }
}
