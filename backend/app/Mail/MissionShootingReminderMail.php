<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/** Email la veille du tournage d'une mission : Producteur (vouvoiement) et Faces retenues (tutoiement). */
final class MissionShootingReminderMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
        public readonly bool $forProducer,
    ) {}

    protected function subjectLine(): string
    {
        return $this->forProducer
            ? 'Rappel : le tournage de « '.$this->mission->titre.' » a lieu demain'
            : 'Rappel : ton tournage « '.$this->mission->titre.' » a lieu demain';
    }

    public function content(): Content
    {
        $path = $this->forProducer
            ? "/producer/missions/{$this->mission->uuid}?tab=presences"
            : "/face/missions/{$this->mission->uuid}";

        return new Content(
            view: 'emails.mission-shooting-reminder',
            with: [
                'forProducer' => $this->forProducer,
                'missionTitle' => $this->mission->titre,
                'shootingDate' => $this->mission->date_tournage?->locale('fr')->translatedFormat('l d F Y'),
                'missionLocation' => $this->mission->lieu,
                'missionDuration' => $this->mission->duree,
                'missionUrl' => rtrim((string) config('app.frontend_url'), '/').$path,
            ],
        );
    }
}
