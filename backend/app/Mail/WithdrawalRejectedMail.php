<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
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
                'userDisplayName' => $this->userDisplayName(),
                'formattedAmount' => number_format((int) $this->withdrawalRequest->amount, 0, ',', ' '),
            ],
        );
    }

    private function userDisplayName(): string
    {
        /** @var User|null $user */
        $user = $this->withdrawalRequest->user;

        return (string) (
            data_get($user, 'userable.display_name')
            ?? data_get($user, 'userable.prenom')
            ?? ($user instanceof User ? $user->email : null)
            ?? 'Utilisateur'
        );
    }
}
