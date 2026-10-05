<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhookEvent;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Provider callbacks. Nothing here trusts the request: the signature is verified
 * first (over the exact payload string) and every event id is stored once, so a
 * repeated delivery is acknowledged but applied only a single time.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentService $payments): JsonResponse
    {
        $provider = match ($gateway) {
            'razorpay' => app(RazorpayGateway::class),
            'fake' => app(FakePaymentGateway::class),
            default => null,
        };

        if ($provider === null) {
            return response()->json(['status' => 'unknown_gateway'], 404);
        }

        // Razorpay posts raw JSON; the local demo posts the same JSON as a form field.
        $payload = $request->filled('payload')
            ? (string) $request->input('payload')
            : $request->getContent();

        $signature = $request->header('X-Razorpay-Signature') ?? $request->input('signature');

        if (! $provider->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Rejected payment webhook with an invalid signature.', ['gateway' => $gateway]);

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $event = $provider->parseWebhook($payload, $request->headers->all());

        try {
            $record = PaymentWebhookEvent::query()->create([
                'gateway' => $provider->name(),
                'event_id' => $event->id,
                'type' => $event->type,
                'status' => PaymentWebhookEvent::STATUS_RECEIVED,
                'payload' => $event->payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate', 'event_id' => $event->id]);
        }

        try {
            $outcome = $payments->handleWebhook($event);

            $record->forceFill([
                'status' => $outcome === 'processed'
                    ? PaymentWebhookEvent::STATUS_PROCESSED
                    : PaymentWebhookEvent::STATUS_IGNORED,
                'processed_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $record->forceFill([
                'status' => PaymentWebhookEvent::STATUS_FAILED,
                'error' => $exception->getMessage(),
                'processed_at' => now(),
            ])->save();

            Log::error('Payment webhook failed to process.', [
                'gateway' => $provider->name(),
                'event_id' => $event->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'failed'], 500);
        }

        return response()->json(['status' => $outcome, 'event_id' => $event->id]);
    }
}
