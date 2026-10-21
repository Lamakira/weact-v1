<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingCommissionPaid;
use App\Mail\BookingPaidProducerMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal e-mail ADDITIF sur BookingCommissionPaid (équivalent UGC de BookingPaid) : accusé de réception
 * du règlement au Producteur. La Face a déjà son e-mail (SendUgcCommissionPaidEmail) : pas de doublon.
 * Dispatché DANS la transaction de UgcCommissionPaymentService::markBookingCommissionPaid ⇒ `afterCommit`,
 * non-fatal.
 */
#[AsEventListener(event: BookingCommissionPaid::class)]
final class SendBookingCommissionPaidProducerEmail
{
    public function handle(BookingCommissionPaid $event): void
    {
        try {
            $booking = $event->booking;
            $booking->loadMissing('producer.userable', 'face.userable');

            $producerEmail = trim((string) $booking->producer?->email);
            if ($producerEmail === '') {
                return;
            }

            Mail::to($producerEmail)->queue((new BookingPaidProducerMail($booking, ugc: true))->afterCommit());
        } catch (\Throwable $e) {
            Log::warning('BookingPaidProducerMail (UGC) queue failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
