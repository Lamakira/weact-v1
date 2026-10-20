<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use App\Models\MissionPayment;
use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement) : sélection de mission non payée, annulée automatiquement à l'échéance. */
final class MissionPaymentReminderMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
        public readonly MissionPayment $payment,
        public readonly string $deadline,
    ) {}

    protected function subjectLine(): string
    {
        return 'Plus que quelques heures pour payer votre sélection « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-payment-reminder',
            with: [
                'missionTitle' => $this->mission->titre,
                'deadline' => $this->deadline,
                'formattedAmount' => number_format((int) $this->payment->montant_total_producteur, 0, ',', ' ').' XOF',
                'candidaturesUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/missions/{$this->mission->uuid}?tab=candidatures",
            ],
        );
    }
}
