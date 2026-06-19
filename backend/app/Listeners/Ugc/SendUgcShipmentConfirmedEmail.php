<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\ShipmentConfirmed;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcShipmentConfirmedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-1, D-7.1.a) sur ShipmentConfirmed : prévient la Face
 * par mail que son produit est expédié. Le listener in-app NotifyFaceOnShipmentConfirmed
 * reste intouché. Non-fatal (D-7.1.d) : un envoi raté ne casse jamais la transaction
 * métier déjà committée (dispatch post-commit).
 */
#[AsEventListener(event: ShipmentConfirmed::class)]
final class SendUgcShipmentConfirmedEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(ShipmentConfirmed $event): void
    {
        try {
            $shipment = $event->shipment;
            $owner = $this->ugcOwnerFrom($shipment);
            $email = $this->faceEmailFor($owner);

            if ($email === '') {
                return; // owner/mission/email manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcShipmentConfirmedMail(
                shipment: $shipment,
                faceName: $this->faceNameFor($owner),
                productName: $this->productNameFor($owner),
                producerName: $this->producerNameFor($owner),
                dealUrl: $this->faceDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcShipmentConfirmedMail queue failed', [
                'shipment_id' => $event->shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
