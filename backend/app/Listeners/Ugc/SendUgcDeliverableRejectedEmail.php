<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\DeliverableRejected;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcDeliverableRejectedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-1, D-7.1.a) sur DeliverableRejected : prévient la
 * Face par mail que son livrable est refusé (avec le motif). Le listener in-app
 * NotifyFaceOnDeliverableRejected reste intouché. Non-fatal (D-7.1.d).
 */
#[AsEventListener(event: DeliverableRejected::class)]
final class SendUgcDeliverableRejectedEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(DeliverableRejected $event): void
    {
        try {
            $deliverable = $event->deliverable;
            $owner = $this->ugcOwnerFrom($deliverable);
            $email = $this->faceEmailFor($owner);

            if ($email === '') {
                return; // owner/mission/email manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcDeliverableRejectedMail(
                deliverable: $deliverable,
                faceName: $this->faceNameFor($owner),
                productName: $this->productNameFor($owner),
                dealUrl: $this->faceDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcDeliverableRejectedMail queue failed', [
                'deliverable_id' => $event->deliverable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
