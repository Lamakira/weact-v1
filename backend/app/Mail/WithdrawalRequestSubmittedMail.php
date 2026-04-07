<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WithdrawalRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly WithdrawalRequest $withdrawalRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Nouvelle demande de retrait - %s %s XOF',
                $this->userDisplayName(),
                $this->formattedAmount()
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.withdrawal-submitted',
            with: [
                'withdrawalRequest' => $this->withdrawalRequest,
                'userDisplayName' => $this->userDisplayName(),
                'formattedAmount' => $this->formattedAmount(),
                'adminUrl' => rtrim((string) config('app.frontend_url'), '/') . '/admin/finance',
            ],
        );
    }

    private function userDisplayName(): string
    {
        return (string) (
            data_get($this->withdrawalRequest->user, 'userable.display_name')
            ?? data_get($this->withdrawalRequest->user, 'userable.prenom')
            ?? $this->withdrawalRequest->user?->email
            ?? 'Utilisateur'
        );
    }

    private function formattedAmount(): string
    {
        return number_format((int) $this->withdrawalRequest->amount, 0, ',', ' ');
    }
}
