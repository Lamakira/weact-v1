<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\DeliverableRetoucheRequested;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcDeliverableRetoucheRequestedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-1, D-7.1.a) sur DeliverableRetoucheRequested :
 * prévient la Face par mail qu'une retouche est demandée (avec le motif). Le
 * listener in-app NotifyFaceOnDeliverableRetoucheRequested reste intouché.
 * Non-fatal (D-7.1.d).
 */
#[AsEventListener(event: DeliverableRetoucheRequested::class)]
final class SendUgcDeliverableRetoucheRequestedEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(DeliverableRetoucheRequested $event): void
    {
        try {
            $deliverable = $event->deliverable;
            $owner = $this->ugcOwnerFrom($deliverable);
            $email = $this->faceEmailFor($owner);

            if ($email === '') {
                return; // owner/mission/email manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcDeliverableRetoucheRequestedMail(
                deliverable: $deliverable,
                faceName: $this->faceNameFor($owner),
                productName: $this->productNameFor($owner),
                dealUrl: $this->faceDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcDeliverableRetoucheRequestedMail queue failed', [
                'deliverable_id' => $event->deliverable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
