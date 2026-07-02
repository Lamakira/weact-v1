<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * Email Face (tutoiement, D-7.1.e) : l'échéance d'upload du livrable approche (ugc-7-3, D-7.3.d).
 * Sujet + contenu gradués par $level (miroir NotifyFaceOnDeadlineApproaching::message).
 */
final class UgcDeadlineApproachingMail extends BaseMail
{
    public function __construct(
        public readonly string $faceName,
        public readonly string $productName,
        public readonly string $kindLabel,
        public readonly string $remaining,
        public readonly int $level,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return match (true) {
            $this->level >= 3 => '⏰ Dernière ligne droite pour ta vidéo UGC',
            $this->level === 2 => 'Ton échéance UGC approche ⏳',
            default => 'Pense à déposer ta vidéo UGC ⏳',
        };
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-deadline-approaching',
            with: [
                'faceName' => $this->faceName,
                'productName' => $this->productName,
                'kindLabel' => $this->kindLabel,
                'remaining' => $this->remaining,
                'level' => $this->level,
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
