<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\HandleFedapayWebhook;
use App\Models\FedapayWebhookEvent;
use App\Services\FedapayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FedapayWebhookController extends Controller
{
    public function __construct(
        private readonly FedapayService $fedapayService,
    ) {}

    /**
     * Handle incoming Fedapay webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-FEDAPAY-SIGNATURE', '');

        // Verify signature
        try {
            $event = $this->fedapayService->verifyWebhookSignature($payload, $signature);
        } catch (\Throwable $e) {
            Log::warning('Fedapay webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $eventId = (string) ($event->id ?? $event->data->id ?? '');
        $eventName = $event->name ?? '';

        // Idempotency check
        $webhookEvent = FedapayWebhookEvent::firstOrCreate(
            ['fedapay_event_id' => $eventId],
            [
                'event_name' => $eventName,
                'payload' => json_decode($payload, true),
                'status' => 'received',
            ]
        );

        // Already processed — return 200 immediately
        if ($webhookEvent->status === 'processed') {
            return response()->json(['status' => 'already_processed']);
        }

        // Dispatch queued job for processing
        HandleFedapayWebhook::dispatch($webhookEvent->id, $eventName, json_decode($payload, true));

        return response()->json(['status' => 'received']);
    }
}
