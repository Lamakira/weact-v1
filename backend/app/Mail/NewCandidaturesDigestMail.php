<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Producteur (vouvoiement) : récapitulatif groupé des nouvelles candidatures d'une mission
 * depuis le dernier envoi, au plus un par mission et par heure (lot D5).
 */
final class NewCandidaturesDigestMail extends BaseMail
{
    /**
     * @param  list<array{name: string, city: ?string}>  $faces  Jusqu'à 5 Faces parmi les nouvelles candidatures.
     */
    public function __construct(
        public readonly Mission $mission,
        public readonly int $newCount,
        public readonly array $faces,
        public readonly int $totalPending,
    ) {}

    protected function subjectLine(): string
    {
        return 'Nouvelles candidatures pour « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-candidatures-digest',
            with: [
                'missionTitle' => $this->mission->titre,
                'newCount' => $this->newCount,
                'faces' => $this->faces,
                'otherCount' => max(0, $this->newCount - count($this->faces)),
                'totalPending' => $this->totalPending,
                'candidaturesUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/missions/{$this->mission->uuid}?tab=candidatures",
            ],
        );
    }
}
