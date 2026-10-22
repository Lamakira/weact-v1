<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Producteur (vouvoiement) : la Face conteste l'absence déclarée ; les fonds restent
 * bloqués jusqu'à la décision de l'équipe WEACT (lot D5).
 */
final class MissionAttendanceDisputedMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
        public readonly string $faceName,
        public readonly int $amount,
    ) {}

    protected function subjectLine(): string
    {
        return 'Une Face conteste son absence à « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-attendance-disputed',
            with: [
                'missionTitle' => $this->mission->titre,
                'faceName' => $this->faceName,
                'formattedAmount' => \App\Support\Money::format($this->amount),
                'missionUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/missions/{$this->mission->uuid}",
            ],
        );
    }
}
