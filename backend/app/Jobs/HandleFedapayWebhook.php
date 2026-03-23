<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Booking;
use App\Models\FedapayWebhookEvent;
use App\Models\MissionPayment;
use App\Services\BookingService;
use App\Services\MissionPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class HandleFedapayWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $webhookEventId,
        public readonly string $eventName,
        public readonly array $payload,
    ) {}

    public function handle(BookingService $bookingService, MissionPaymentService $missionPaymentService): void
    {
        $webhookEvent = FedapayWebhookEvent::find($this->webhookEventId);

        if (! $webhookEvent || $webhookEvent->status === 'processed') {
            return;
        }

        $transactionData = $this->payload['entity'] ?? $this->payload['data'] ?? [];
        $transactionId = $transactionData['id'] ?? null;
        $fedapayRef = (string) ($transactionData['reference'] ?? $transactionId ?? '');

        if (! $transactionId) {
            Log::warning('Fedapay webhook missing transaction ID', [
                'event_name' => $this->eventName,
                'webhook_event_id' => $this->webhookEventId,
            ]);
            $this->markProcessed($webhookEvent);

            return;
        }

        // Try to find a Booking first
        $booking = Booking::where('fedapay_transaction_id', $transactionId)->first();

        if ($booking) {
            match ($this->eventName) {
                'transaction.approved' => $bookingService->markAsPaid($booking, $fedapayRef),
                'transaction.declined', 'transaction.canceled' => $bookingService->markPaymentFailed(
                    $booking,
                    $fedapayRef,
                    "Payment {$this->eventName}"
                ),
                default => Log::info('Fedapay webhook: unhandled event', ['event' => $this->eventName]),
            };

            $this->markProcessed($webhookEvent);

            return;
        }

        // Try to find a MissionPayment
        $missionPayment = MissionPayment::where('fedapay_transaction_id', (string) $transactionId)->first();

        if ($missionPayment) {
            match ($this->eventName) {
                'transaction.approved' => $missionPaymentService->markAsPaid($missionPayment, $fedapayRef),
                'transaction.declined', 'transaction.canceled' => Log::info('Fedapay webhook: mission payment declined/canceled', [
                    'mission_payment_id' => $missionPayment->id,
                    'event' => $this->eventName,
                ]),
                default => Log::info('Fedapay webhook: unhandled event', ['event' => $this->eventName]),
            };

            $this->markProcessed($webhookEvent);

            return;
        }

        Log::warning('Fedapay webhook: no booking or mission payment found for transaction', [
            'transaction_id' => $transactionId,
            'event_name' => $this->eventName,
        ]);

        $this->markProcessed($webhookEvent);
    }

    private function markProcessed(FedapayWebhookEvent $webhookEvent): void
    {
        $webhookEvent->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }
}
