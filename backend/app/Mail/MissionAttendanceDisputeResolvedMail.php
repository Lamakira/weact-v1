<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\DisputeResolutionOutcome;
use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/**
 * Email aux deux parties (vouvoiement) : issue d'une contestation d'absence, tranchée par l'équipe
 * WEACT. Le texte dépend du destinataire (Face ou Producteur) et de l'issue (lot D5).
 */
final class MissionAttendanceDisputeResolvedMail extends BaseMail
{
    public const RECIPIENT_FACE = 'face';

    public const RECIPIENT_PRODUCER = 'producer';

    public function __construct(
        public readonly Mission $mission,
        public readonly string $recipient,
        public readonly DisputeResolutionOutcome $outcome,
        public readonly string $recipientName,
        public readonly int $amount,
    ) {}

    protected function subjectLine(): string
    {
        return 'Litige d\'absence tranché pour « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        $isFace = $this->recipient === self::RECIPIENT_FACE;

        return new Content(
            view: 'emails.mission-attendance-dispute-resolved',
            with: [
                'recipientName' => $this->recipientName,
                'missionTitle' => $this->mission->titre,
                'isFace' => $isFace,
                'favorFace' => $this->outcome === DisputeResolutionOutcome::FavorFace,
                'formattedAmount' => \App\Support\Money::format($this->amount),
                'missionUrl' => rtrim((string) config('app.frontend_url'), '/').($isFace ? '/face' : '/producer')."/missions/{$this->mission->uuid}",
            ],
        );
    }
}
