<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement) : des candidatures attendent sa réponse, échéance dans 24 h (lot B6). */
final class CandidatureResponseReminderMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
        public readonly int $pendingCount,
        public readonly string $deadlineLabel,
    ) {}

    protected function subjectLine(): string
    {
        return 'Des candidatures attendent votre réponse';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidature-response-reminder',
            with: [
                'missionTitle' => $this->mission->titre,
                'pendingCount' => $this->pendingCount,
                'deadlineLabel' => $this->deadlineLabel,
                'candidaturesUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/missions/{$this->mission->uuid}/candidatures",
            ],
        );
    }
}
