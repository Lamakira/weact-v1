<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement) : une Face n'a pas confirmé, sa place est libérée et remboursée sur le wallet. */
final class MissionFaceUnconfirmedMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
        public readonly string $faceName,
        public readonly int $refundedAmount,
    ) {}

    protected function subjectLine(): string
    {
        return $this->faceName.' n\'a pas confirmé sa participation à « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-face-unconfirmed',
            with: [
                'missionTitle' => $this->mission->titre,
                'faceName' => $this->faceName,
                'formattedAmount' => number_format($this->refundedAmount, 0, ',', ' ').' FCFA',
                'candidaturesUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/missions/{$this->mission->uuid}/candidatures",
            ],
        );
    }
}
