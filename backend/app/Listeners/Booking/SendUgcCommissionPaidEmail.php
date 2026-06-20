<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingCommissionPaid;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcCommissionPaidMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-3) sur BookingCommissionPaid : prévient la Face que le
 * Producteur a réglé la commission → elle doit ACCEPTER le deal pour débloquer la
 * réception. Event UGC-only par construction (UgcCommissionPaymentService:196). L'in-app
 * NotifyFaceOnUgcCommissionPaid reste intouché. Non-fatal (dispatch post-commit).
 */
#[AsEventListener(event: BookingCommissionPaid::class)]
final class SendUgcCommissionPaidEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(BookingCommissionPaid $event): void
    {
        try {
            $owner = $event->booking;

            $email = $this->faceEmailFor($owner);
            if ($email === '') {
                return; // email Face manquant → skip silencieux
            }

            Mail::to($email)->queue(new UgcCommissionPaidMail(
                faceName: $this->faceNameFor($owner),
                producerName: $this->producerNameFor($owner),
                productName: $this->productNameFor($owner),
                dealUrl: $this->faceDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcCommissionPaidMail queue failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
