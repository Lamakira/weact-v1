<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\DeliverableValidated;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcDeliverableValidatedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-1, D-7.1.a) sur DeliverableValidated : prévient la
 * Face par mail que son livrable est validé. Le listener in-app
 * NotifyFaceOnDeliverableValidated reste intouché. Non-fatal (D-7.1.d).
 */
#[AsEventListener(event: DeliverableValidated::class)]
final class SendUgcDeliverableValidatedEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(DeliverableValidated $event): void
    {
        try {
            $deliverable = $event->deliverable;
            $owner = $this->ugcOwnerFrom($deliverable);
            $email = $this->faceEmailFor($owner);

            if ($email === '') {
                return; // owner/mission/email manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcDeliverableValidatedMail(
                deliverable: $deliverable,
                faceName: $this->faceNameFor($owner),
                productName: $this->productNameFor($owner),
                dealUrl: $this->faceDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcDeliverableValidatedMail queue failed', [
                'deliverable_id' => $event->deliverable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
