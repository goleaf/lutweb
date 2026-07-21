<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PayPalWebhookProcessingStatus;
use App\Enums\PayPalWebhookVerificationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayPalWebhook;
use App\Models\PayPalWebhookEvent;
use App\Services\PayPal\PayPalApiException;
use App\Services\Webhooks\VerifyPayPalWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class PayPalWebhookController extends Controller
{
    public function __invoke(Request $request, VerifyPayPalWebhookSignature $verifier): JsonResponse
    {
        $rawBody = $request->getContent();

        if (strlen($rawBody) > (int) config('paypal.webhook_body_max_bytes', 1024 * 1024)) {
            return response()->json(['message' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (! $request->isJson()) {
            return response()->json(['message' => 'JSON is required.'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $headers = $this->paypalHeaders($request);

        if (count($headers) !== 5) {
            return response()->json(['message' => 'Missing PayPal transmission headers.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $verified = $verifier->verify($rawBody, $headers);
        } catch (PayPalApiException|JsonException) {
            return response()->json(['message' => 'Webhook verification is temporarily unavailable.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $verified) {
            return response()->json(['message' => 'Invalid PayPal webhook signature.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $paypalEventId = is_string($payload['id'] ?? null) ? $payload['id'] : null;
        $eventType = is_string($payload['event_type'] ?? null) ? $payload['event_type'] : null;

        if ($paypalEventId === null || $eventType === null) {
            return response()->json(['message' => 'Missing PayPal event identifiers.'], Response::HTTP_BAD_REQUEST);
        }

        $event = DB::transaction(function () use ($payload, $rawBody, $headers, $paypalEventId, $eventType): PayPalWebhookEvent {
            $existing = PayPalWebhookEvent::query()
                ->where('paypal_event_id', $paypalEventId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PayPalWebhookEvent) {
                return $existing;
            }

            return PayPalWebhookEvent::query()->create([
                'paypal_event_id' => $paypalEventId,
                'event_type' => $eventType,
                'resource_type' => is_string($payload['resource_type'] ?? null) ? $payload['resource_type'] : null,
                'transmission_id' => $headers['transmission_id'],
                'transmission_time' => $headers['transmission_time'],
                'verification_status' => PayPalWebhookVerificationStatus::Verified,
                'processing_status' => PayPalWebhookProcessingStatus::Pending,
                'payload_sha256' => hash('sha256', $rawBody),
                'encrypted_payload' => $rawBody,
            ]);
        });

        if ($event->wasRecentlyCreated) {
            ProcessPayPalWebhook::dispatch($event->id);
        }

        return response()->json(['status' => 'accepted']);
    }

    /**
     * @return array<string, string>
     */
    private function paypalHeaders(Request $request): array
    {
        return collect([
            'transmission_id' => $request->headers->get('PayPal-Transmission-Id'),
            'transmission_time' => $request->headers->get('PayPal-Transmission-Time'),
            'transmission_sig' => $request->headers->get('PayPal-Transmission-Sig'),
            'cert_url' => $request->headers->get('PayPal-Cert-Url'),
            'auth_algo' => $request->headers->get('PayPal-Auth-Algo'),
        ])
            ->filter(fn (?string $value): bool => is_string($value) && $value !== '')
            ->all();
    }
}
