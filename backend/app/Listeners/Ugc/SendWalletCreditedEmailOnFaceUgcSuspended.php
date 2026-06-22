<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Enums\EscrowStatus;
use App\Enums\WalletCreditMotif;
use App\Events\FaceUgcSuspended;
use App\Mail\WalletCreditedMail;
use App\Models\Booking;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-5) sur FaceUgcSuspended : confirme au Producteur le crédit
 * wallet du remboursement escrow (montant_face_recoit) suite à la suspension de la Face.
 * BOOKING-ONLY (escrow = hybride booking ; pas de séquestre mission). Garde « refund réel »
 * (escrow Refunded + montant_face_recoit > 0) = produit-seul / no-refund → skip. Réutilise
 * WalletCreditedMail (motif UgcSuspensionRefund). Post-commit ⇒ non-fatal try/catch.
 */
#[AsEventListener(event: FaceUgcSuspended::class)]
final class SendWalletCreditedEmailOnFaceUgcSuspended
{
    public function handle(FaceUgcSuspended $event): void
    {
        try {
            $owner = $event->shipment->owner;
            if (! $owner instanceof Booking) {
                return; // mission (Candidature) : aucun refund escrow Producteur
            }

            // Garde « refund réellement eu lieu » (D-7.5.c) : refundUgcSuspensionToProducer
            // a posé l'escrow à Refunded + crédité montant_face_recoit. Produit-seul (pas
            // d'escrow) ou pas de net Face ⇒ pas d'email "0 XOF remboursés".
            $escrowRefunded = $owner->escrowTransaction()
                ->where('status', EscrowStatus::Refunded->value)
                ->exists();
            if (! $escrowRefunded || (int) ($owner->montant_face_recoit ?? 0) <= 0) {
                return;
            }

            $owner->loadMissing('producer.userable');
            $producer = $owner->producer->userable instanceof Producer
                ? $owner->producer->userable
                : null;
            $producerEmail = trim((string) $owner->producer->email);
            if ($producer === null || $producerEmail === '') {
                return;
            }

            $newBalance = (int) (User::find($owner->producer_id)->balance ?? 0);

            Mail::to($producerEmail)->queue(new WalletCreditedMail(
                producer: $producer,
                amount: (int) $owner->montant_face_recoit,
                motif: WalletCreditMotif::UgcSuspensionRefund,
                newBalance: $newBalance,
            ));
        } catch (\Throwable $e) {
            Log::warning('WalletCreditedMail (ugc suspension) queue failed', [
                'shipment_id' => $event->shipment->id,
                'owner_type' => $event->shipment->owner_type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
