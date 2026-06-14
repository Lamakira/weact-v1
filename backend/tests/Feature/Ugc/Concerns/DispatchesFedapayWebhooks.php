<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc\Concerns;

use App\Jobs\HandleFedapayWebhook;
use App\Models\FedapayWebhookEvent;
use App\Services\BookingService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\MissionPaymentService;
use App\Services\Ugc\UgcCommissionPaymentService;
use App\Services\WalletService;

/**
 * Dispatch synchrone d'un webhook FedaPay vers HandleFedapayWebhook (dédup des tests UGC, ugc-3-5 Item 5).
 * Variante 4-arg, superset des appels 3-arg : le 4ᵉ paramètre injecte un `entity.status` optionnel.
 */
trait DispatchesFedapayWebhooks
{
    protected int $webhookSeq = 0;

    protected function dispatchWebhook(string $eventName, int $transactionId, string $reference, ?string $transactionStatus = null): void
    {
        $this->webhookSeq++;
        $entity = ['id' => $transactionId, 'reference' => $reference];

        if ($transactionStatus !== null) {
            $entity['status'] = $transactionStatus;
        }

        $payload = ['entity' => $entity];

        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => "evt_{$transactionId}_{$this->webhookSeq}",
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'received',
        ]);

        (new HandleFedapayWebhook($webhookEvent->id, $eventName, $payload))->handle(
            app(BookingService::class),
            app(MissionPaymentService::class),
            app(WalletService::class),
            app(FaceSubscriptionPaymentService::class),
            app(UgcCommissionPaymentService::class),
        );
    }
}
