<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\FinancialEventType;
use App\Models\Booking;
use App\Models\FedapayWebhookEvent;
use App\Models\FinancialEvent;
use App\Models\MissionPayment;
use App\Models\WalletTransaction;
use App\Services\BookingService;
use App\Services\MissionPaymentService;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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

    public function handle(BookingService $bookingService, MissionPaymentService $missionPaymentService, WalletService $walletService): void
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

        // Try to find a withdrawal FinancialEvent (payout.sent / payout.failed)
        $financialEvent = FinancialEvent::where('fedapay_ref', (string) $transactionId)
            ->where('type', FinancialEventType::Withdrawal)
            ->first();

        if ($financialEvent) {
            $this->handlePayoutWebhook($financialEvent, $walletService);
            $this->markProcessed($webhookEvent);

            return;
        }

        Log::warning('Fedapay webhook: no booking, mission payment or withdrawal found for transaction', [
            'transaction_id' => $transactionId,
            'event_name' => $this->eventName,
        ]);

        $this->markProcessed($webhookEvent);
    }

    private function handlePayoutWebhook(FinancialEvent $financialEvent, WalletService $walletService): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($financialEvent->metadata) ? $financialEvent->metadata : [];
        $walletTransactionId = isset($metadata['wallet_transaction_id']) ? (int) $metadata['wallet_transaction_id'] : null;
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : null;

        if ($walletTransactionId === null || $userId === null) {
            Log::warning('Fedapay payout webhook: missing metadata on FinancialEvent', [
                'financial_event_id' => $financialEvent->id,
                'event_name' => $this->eventName,
            ]);

            return;
        }

        $walletTx = WalletTransaction::find($walletTransactionId);

        if (! $walletTx || $walletTx->status !== 'pending') {
            return;
        }

        match ($this->eventName) {
            'payout.sent' => $walletTx->update(['status' => 'completed']),
            'payout.failed' => DB::transaction(function () use ($walletTx, $userId, $financialEvent, $walletService): void {
                $walletTx->update(['status' => 'failed']);
                $walletService->creditDirect(
                    userId: $userId,
                    amount: $financialEvent->amount,
                    description: "Remboursement retrait échoué — ref #{$financialEvent->fedapay_ref}",
                );
                Log::info('Fedapay payout failed — balance refunded', [
                    'user_id' => $userId,
                    'amount' => $financialEvent->amount,
                ]);
            }),
            default => Log::info('Fedapay payout webhook: unhandled event', ['event' => $this->eventName]),
        };
    }

    private function markProcessed(FedapayWebhookEvent $webhookEvent): void
    {
        $webhookEvent->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }
}
