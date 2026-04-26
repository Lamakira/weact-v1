<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Mail\Mailables\Content;

final class FaceConfirmedMail extends BaseMail
{
    public function __construct(
        public readonly Producer $producer,
        public readonly Face $face,
        public readonly Mission $mission,
    ) {}

    protected function subjectLine(): string
    {
        return $this->resolveFaceDisplayName().' a confirmé sa participation à la mission « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.face-confirmed',
            with: [
                'producerName' => $this->resolveProducerName(),
                'faceName' => $this->resolveFaceDisplayName(),
                'missionTitle' => $this->mission->titre,
                'shootingDate' => $this->formatShootingDate(),
                'candidaturesUrl' => $this->buildCandidaturesUrl(),
            ],
        );
    }

    private function resolveProducerName(): string
    {
        $name = trim((string) $this->producer->display_name);

        return $name !== '' ? $name : 'Producteur';
    }

    private function resolveFaceDisplayName(): string
    {
        $name = trim((string) $this->face->display_name);

        return $name !== '' ? $name : 'Face';
    }

    private function formatShootingDate(): string
    {
        return $this->mission->date_tournage?->locale('fr')->translatedFormat('l d F Y')
            ?? 'À déterminer';
    }

    private function buildCandidaturesUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/producer/missions/'.$this->mission->uuid.'/candidatures';
    }
}
