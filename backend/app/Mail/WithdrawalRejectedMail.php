<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WithdrawalRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly WithdrawalRequest $withdrawalRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Votre demande de retrait n'a pas pu etre traitee",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.withdrawal-rejected',
            with: [
                'withdrawalRequest' => $this->withdrawalRequest,
                'faceFirstName' => (string) data_get($this->withdrawalRequest->user, 'userable.prenom', 'Face'),
                'formattedAmount' => number_format((int) $this->withdrawalRequest->amount, 0, ',', ' '),
            ],
        );
    }
}
