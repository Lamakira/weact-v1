<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Enums\UgcRefundReason;
use App\Events\UgcCommissionRefundRequested;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: UgcCommissionRefundRequested::class)]
class NotifyProducerOnUgcRefundRequested
{
    /**
     * Notifie le Producteur que le remboursement de sa commission est en cours.
     * Non-fatal : la demande est déjà persistée (commission_refund_requested_at).
     */
    public function handle(UgcCommissionRefundRequested $event): void
    {
        try {
            $owner = $event->owner;

            // bookings.producer_id EST un users.id ; missions.producer_id est un producers.id.
            $producerUserId = $owner instanceof Mission
                ? User::where('userable_type', Producer::class)
                    ->where('userable_id', $owner->producer_id)
                    ->value('id')
                : $owner->producer_id;

            if ($producerUserId === null) {
                return;
            }

            $amount = number_format((int) $owner->commission_ugc, 0, ',', ' ');

            if ($owner instanceof Mission) {
                $message = $owner->commission_refund_reason === UgcRefundReason::MissionDeadlineExpired
                    ? "Votre mission UGC « {$owner->titre} » s'est terminée sans participant — le remboursement de votre commission ({$amount} FCFA) est en cours."
                    : "Le remboursement de votre commission UGC ({$amount} FCFA) est en cours.";
            } else {
                $message = match ($owner->commission_refund_reason) {
                    UgcRefundReason::Refused => "La Face a refusé votre deal UGC « {$owner->nom_produit} » — le remboursement de votre commission ({$amount} FCFA) est en cours.",
                    UgcRefundReason::AcceptanceWindowExpired => "Votre deal UGC « {$owner->nom_produit} » a expiré sans acceptation — le remboursement de votre commission ({$amount} FCFA) est en cours.",
                    default => "Le remboursement de votre commission UGC ({$amount} FCFA) est en cours.",
                };
            }

            Notification::create([
                'user_id' => $producerUserId,
                'type' => 'ugc_commission_refund_requested',
                'data' => [
                    'message' => $message,
                    'url' => $owner instanceof Mission ? '/producer/missions' : "/producer/bookings/{$owner->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('UgcCommissionRefundRequested notification failed', [
                'owner_id' => $event->owner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
