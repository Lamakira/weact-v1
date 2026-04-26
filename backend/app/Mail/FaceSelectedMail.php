<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

final class FaceSelectedMail extends BaseMail
{
    public function __construct(
        public readonly Face $face,
        public readonly Mission $mission,
        public readonly string $producerName,
        public readonly int $amount,
    ) {}

    protected function subjectLine(): string
    {
        return 'Vous avez été sélectionnée pour la mission « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.face-selected',
            with: [
                'faceFirstName' => $this->resolveFaceFirstName(),
                'missionTitle' => $this->mission->titre,
                'producerName' => $this->producerName,
                'shootingDate' => $this->formatShootingDate(),
                'formattedAmount' => $this->formatAmount(),
                'confirmUrl' => $this->buildConfirmUrl(),
            ],
        );
    }

    private function resolveFaceFirstName(): string
    {
        $prenom = trim((string) $this->face->prenom);

        return $prenom !== '' ? $prenom : $this->face->display_name;
    }

    private function formatShootingDate(): string
    {
        return $this->mission->date_tournage?->locale('fr')->translatedFormat('l d F Y')
            ?? 'À déterminer';
    }

    private function formatAmount(): string
    {
        return number_format($this->amount, 0, ',', ' ').' XOF';
    }

    private function buildConfirmUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/face/candidatures';
    }
}
