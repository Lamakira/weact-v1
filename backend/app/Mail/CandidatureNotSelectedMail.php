<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * Email Face (vouvoiement) : sa candidature n'a pas abouti sans refus manuel du Producteur (lot D5).
 * Deux motifs : le Producteur a retenu et payé d'autres Faces, ou il n'a pas répondu dans le délai.
 * Le refus manuel garde CandidatureRefusedMail.
 */
final class CandidatureNotSelectedMail extends BaseMail
{
    public const REASON_SELECTION_MADE = 'selection_made';

    public const REASON_NO_RESPONSE = 'producer_no_response';

    public function __construct(
        public readonly string $faceName,
        public readonly string $missionTitle,
        public readonly string $reason,
        public readonly string $browseUrl,
    ) {}

    protected function subjectLine(): string
    {
        return "Votre candidature n'a pas été retenue";
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidature-not-selected',
            with: [
                'faceName' => $this->faceName,
                'missionTitle' => $this->missionTitle,
                'noResponse' => $this->reason === self::REASON_NO_RESPONSE,
                'browseUrl' => $this->browseUrl,
            ],
        );
    }
}
