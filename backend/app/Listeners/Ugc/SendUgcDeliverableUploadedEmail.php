<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\DeliverableUploaded;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcDeliverableUploadedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-1, D-7.1.a) sur DeliverableUploaded : prévient le
 * Producteur par mail qu'une vidéo est à valider. Le listener in-app
 * NotifyProducerOnDeliverableUploaded reste intouché. Non-fatal (D-7.1.d).
 */
#[AsEventListener(event: DeliverableUploaded::class)]
final class SendUgcDeliverableUploadedEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(DeliverableUploaded $event): void
    {
        try {
            $deliverable = $event->deliverable;
            $owner = $this->ugcOwnerFrom($deliverable);
            $email = $this->producerEmailFor($owner);

            if ($email === '') {
                return; // owner/mission/email manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcDeliverableUploadedMail(
                deliverable: $deliverable,
                producerName: $this->producerNameFor($owner),
                productName: $this->productNameFor($owner),
                dealUrl: $this->producerDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcDeliverableUploadedMail queue failed', [
                'deliverable_id' => $event->deliverable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
