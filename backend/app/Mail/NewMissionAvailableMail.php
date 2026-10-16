<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\FaceSubscriptionPlan;
use App\Models\Face;
use App\Models\Mission;
use App\Support\MissionAnnouncement;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailables\Content;

/**
 * Avant-première : nouvelle mission publiée, réservée aux Faces abonnées.
 */
final class NewMissionAvailableMail extends BaseMail
{
    public function __construct(
        public readonly Mission $mission,
        public readonly Face $face,
        public readonly FaceSubscriptionPlan $plan,
    ) {}

    protected function subjectLine(): string
    {
        return 'En avant-première : '.$this->mission->titre;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-mission-available',
            with: [
                'faceFirstName' => $this->resolveFaceFirstName(),
                'title' => $this->mission->titre,
                'typeLabel' => MissionAnnouncement::typeLabel($this->mission),
                'lieu' => filled($this->mission->lieu) ? (string) $this->mission->lieu : null,
                'deadlineLabel' => $this->formatDate($this->mission->date_limite_candidature),
                'shootingLabel' => $this->formatDate($this->mission->date_tournage),
                'compensationLabel' => MissionAnnouncement::compensationLabel($this->mission),
                'missionUrl' => $this->buildMissionUrl(),
                'planLabel' => $this->plan->label(),
            ],
        );
    }

    private function resolveFaceFirstName(): string
    {
        $prenom = trim((string) $this->face->prenom);

        return $prenom !== '' ? $prenom : 'Face';
    }

    private function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->locale('fr')->translatedFormat('l d F Y');
    }

    private function buildMissionUrl(): string
    {
        $frontendUrl = config('app.frontend_url');
        $base = is_string($frontendUrl) && $frontendUrl !== '' ? $frontendUrl : (string) config('app.url');

        return rtrim($base, '/').'/face/missions/'.$this->mission->uuid;
    }
}
