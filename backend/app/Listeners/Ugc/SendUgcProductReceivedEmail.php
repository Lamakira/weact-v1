<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\ProductReceived;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcProductReceivedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-1, D-7.1.a) sur ProductReceived : prévient le Producteur
 * par mail que la Face a confirmé la réception. Le listener in-app
 * NotifyProducerOnProductReceived reste intouché. Non-fatal (D-7.1.d).
 */
#[AsEventListener(event: ProductReceived::class)]
final class SendUgcProductReceivedEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(ProductReceived $event): void
    {
        try {
            $shipment = $event->shipment;
            $owner = $this->ugcOwnerFrom($shipment);
            $email = $this->producerEmailFor($owner);

            if ($email === '') {
                return; // owner/mission/email manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcProductReceivedMail(
                shipment: $shipment,
                producerName: $this->producerNameFor($owner),
                productName: $this->productNameFor($owner),
                dealUrl: $this->producerDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcProductReceivedMail queue failed', [
                'shipment_id' => $event->shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
