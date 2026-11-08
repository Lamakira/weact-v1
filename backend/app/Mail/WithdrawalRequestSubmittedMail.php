<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\Money;
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
                'À traiter : demande de retrait de %s (%s)',
                $this->formattedAmount(),
                $this->profileLabel()
            ),
        );
    }

    public function content(): Content
    {
        $pending = WithdrawalRequest::query()->where('status', 'pending');

        return new Content(
            view: 'emails.withdrawal-submitted',
            with: [
                'withdrawalRequest' => $this->withdrawalRequest,
                'userDisplayName' => $this->userDisplayName(),
                'profileLabel' => $this->profileLabel(),
                'formattedAmount' => $this->formattedAmount(),
                'operatorLabel' => $this->operatorLabel(),
                'submittedAt' => $this->withdrawalRequest->created_at
                    ?->copy()
                    ->setTimezone((string) config('app.business_timezone'))
                    ->format('d/m/Y à H:i'),
                'pendingCount' => (clone $pending)->count(),
                'pendingTotal' => Money::format((int) (clone $pending)->sum('amount')),
                'adminUrl' => rtrim((string) config('app.frontend_url'), '/').'/admin/finance',
            ],
        );
    }

    private function userDisplayName(): string
    {
        /** @var User|null $user */
        $user = $this->withdrawalRequest->user;

        $name = data_get($user, 'userable.display_name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return $user instanceof User && $user->email ? $user->email : 'Utilisateur';
    }

    private function profileLabel(): string
    {
        $userable = $this->withdrawalRequest->user->userable;

        return match (true) {
            $userable instanceof Face => 'Face',
            $userable instanceof Producer => 'Producteur',
            default => 'Utilisateur',
        };
    }

    private function operatorLabel(): string
    {
        $mode = (string) $this->withdrawalRequest->payment_mode;

        return match ($mode) {
            'mtn' => 'MTN MoMo',
            'moov' => 'Moov Money',
            'momo_test' => 'Test MoMo',
            default => $mode,
        };
    }

    private function formattedAmount(): string
    {
        return Money::format((int) $this->withdrawalRequest->amount);
    }
}
