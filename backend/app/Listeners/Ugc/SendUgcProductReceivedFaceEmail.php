<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\ProductReceived;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcProductReceivedFaceMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-3, D-7.3.c) sur ProductReceived : prévient la Face que
 * le chrono Unboxing a démarré → elle doit FILMER. 2ᵉ listener email sur l'event
 * (SendUgcProductReceivedEmail → Producteur reste INTACT). Sans date ni dépendance
 * UgcDeadlineService (le délai exact reste in-app, le mail est un coup de pouce).
 * Non-fatal (dispatch post-commit).
 */
#[AsEventListener(event: ProductReceived::class)]
final class SendUgcProductReceivedFaceEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(ProductReceived $event): void
    {
        try {
            $owner = $this->ugcOwnerFrom($event->shipment);

            $email = $this->faceEmailFor($owner);
            if ($email === '') {
                return; // owner/mission/email manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcProductReceivedFaceMail(
                faceName: $this->faceNameFor($owner),
                productName: $this->productNameFor($owner),
                dealUrl: $this->faceDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcProductReceivedFaceMail queue failed', [
                'shipment_id' => $event->shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
