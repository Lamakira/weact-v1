<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement) : sélection de mission annulée faute de paiement dans les 24 h. */
final class MissionSelectionExpiredMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre sélection pour « '.$this->mission->titre.' » a été annulée';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-selection-expired',
            with: [
                'missionTitle' => $this->mission->titre,
                'candidaturesUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/missions/{$this->mission->uuid}?tab=candidatures",
            ],
        );
    }
}
