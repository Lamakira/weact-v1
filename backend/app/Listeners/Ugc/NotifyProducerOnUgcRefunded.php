<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\UgcCommissionRefunded;
use App\Models\Mission;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: UgcCommissionRefunded::class)]
class NotifyProducerOnUgcRefunded
{
    /**
     * Notifie le Producteur que son remboursement a été crédité sur son
     * portefeuille WeAct (settlement wallet synchrone — story 2.6). Le montant
     * reflète ce qui est réellement crédité : le règlement complet
     * (montant_total_producteur) pour un booking, commission_ugc pour une mission
     * (RH.2 — le booking hybride encaisse cash + frais service). Non-fatal :
     * commission_refunded_at + le crédit wallet sont déjà persistés.
     */
    public function handle(UgcCommissionRefunded $event): void
    {
        try {
            $owner = $event->owner;

            // bookings.producer_id EST un users.id ; missions.producer_id est un
            // producers.id → User via la relation Producer::user() (morphOne userable).
            $producerUserId = $owner instanceof Mission
                ? $owner->producer?->user?->getKey()
                : $owner->producer_id;

            if ($producerUserId === null) {
                return;
            }

            // RH.2 : un booking rembourse le RÈGLEMENT COMPLET (montant_total_producteur =
            // cash + frais service en hybride ; = commission en produit-seul) — le crédit wallet
            // (UgcRefundService::settleLockedBooking) porte ce total, pas commission_ugc. La mission
            // reste sur commission_ugc (cash non encaissé — AC8 ugc-rh-2 / ugc-epic-rh-mission).
            $isMission = $owner instanceof Mission;
            $amountInt = $isMission ? (int) $owner->commission_ugc : (int) $owner->montant_total_producteur;
            $amount = number_format($amountInt, 0, ',', ' ');
            $message = $isMission
                ? "Votre commission UGC de {$amount} FCFA a été créditée sur votre portefeuille WeAct."
                : "Votre règlement UGC de {$amount} FCFA a été remboursé sur votre portefeuille WeAct.";

            Notification::create([
                'user_id' => $producerUserId,
                'type' => 'ugc_commission_refunded',
                'data' => [
                    'message' => $message,
                    'url' => '/producer/wallet',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('UgcCommissionRefunded notification failed', [
                'owner_id' => $event->owner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
