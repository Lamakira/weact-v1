<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Producteur (vouvoiement) : son paiement de sélection est confirmé ; les Faces retenues
 * sont prévenues et doivent confirmer leur participation (lot D5).
 */
final class MissionPaymentConfirmedMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
        public readonly int $faceCount,
        public readonly int $totalPaid,
    ) {}

    protected function subjectLine(): string
    {
        return 'Paiement confirmé pour « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-payment-confirmed',
            with: [
                'missionTitle' => $this->mission->titre,
                'faceCount' => $this->faceCount,
                'formattedAmount' => \App\Support\Money::format($this->totalPaid),
                'missionUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/missions/{$this->mission->uuid}",
            ],
        );
    }
}
