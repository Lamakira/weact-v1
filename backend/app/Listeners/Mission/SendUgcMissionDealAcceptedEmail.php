<?php

declare(strict_types=1);

namespace App\Listeners\Mission;

use App\Events\UgcMissionDealAccepted;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcDealAcceptedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-3) sur UgcMissionDealAccepted : prévient le Producteur qu'il
 * doit EXPÉDIER le produit (variante mission de SendUgcBookingAcceptedEmail). UGC-only par
 * construction (event dispatché uniquement à l'acceptation d'un deal mission UGC) ⇒ pas de
 * garde type. Mailable PARTAGÉ UgcDealAcceptedMail (strings-only, D-7.3.e). L'in-app
 * NotifyProducerOnUgcDealAccepted reste intouché. Non-fatal.
 */
#[AsEventListener(event: UgcMissionDealAccepted::class)]
final class SendUgcMissionDealAcceptedEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(UgcMissionDealAccepted $event): void
    {
        try {
            $owner = $event->candidature;

            $email = $this->producerEmailFor($owner);
            if ($email === '') {
                return; // owner/mission/email manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcDealAcceptedMail(
                producerName: $this->producerNameFor($owner),
                faceName: $this->faceNameFor($owner),
                productName: $this->productNameFor($owner),
                dealUrl: $this->producerDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcDealAcceptedMail (candidature) queue failed', [
                'candidature_id' => $event->candidature->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
