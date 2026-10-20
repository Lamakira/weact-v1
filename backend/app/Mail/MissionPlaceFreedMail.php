<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Producteur (vouvoiement) : une Face n'a pas confirmé, sa place est libérée ; le
 * Producteur peut choisir une remplaçante parmi les candidatures non retenues, sinon le
 * budget de la place est remboursé au début du jour de tournage (lot B4).
 */
final class MissionPlaceFreedMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
        public readonly string $faceName,
        public readonly int $placeBudget,
        public readonly string $refundAtLabel,
    ) {}

    protected function subjectLine(): string
    {
        return 'Une place s\'est libérée sur « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-place-freed',
            with: [
                'missionTitle' => $this->mission->titre,
                'faceName' => $this->faceName,
                'formattedAmount' => number_format($this->placeBudget, 0, ',', ' ').' FCFA',
                'refundAtLabel' => $this->refundAtLabel,
                'candidaturesUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/missions/{$this->mission->uuid}?tab=candidatures",
            ],
        );
    }
}
