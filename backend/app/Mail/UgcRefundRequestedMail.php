<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\UgcRefundReason;
use App\Models\Booking;
use App\Models\Mission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UgcRefundRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Booking|Mission $owner,
        public readonly UgcRefundReason $reason,
    ) {}

    public function envelope(): Envelope
    {
        $kind = $this->owner instanceof Booking ? 'Booking' : 'Mission';

        return new Envelope(
            subject: sprintf(
                'Remboursement commission UGC à effectuer — %s #%d — %s FCFA',
                $kind,
                $this->owner->id,
                number_format((int) $this->owner->commission_ugc, 0, ',', ' ')
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-refund-requested',
            with: [
                'owner' => $this->owner,
                'isBooking' => $this->owner instanceof Booking,
                'reasonLabel' => $this->reason->label(),
                'formattedAmount' => number_format((int) $this->owner->commission_ugc, 0, ',', ' '),
                'fedapayTransactionId' => $this->owner->fedapay_transaction_id,
            ],
        );
    }
}
