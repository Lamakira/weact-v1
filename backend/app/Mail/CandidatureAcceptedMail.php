<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** Email Face (tutoiement) : sa candidature UGC est acceptée → reconfirmer la participation (ugc-8-2, D-8.2.g). */
final class CandidatureAcceptedMail extends BaseMail
{
    public function __construct(
        public readonly string $faceName,
        public readonly string $missionTitle,
        public readonly string $productName,
        public readonly string $reconfirmUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre candidature a été acceptée — confirmez votre participation';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidature-accepted',
            with: [
                'faceName' => $this->faceName,
                'missionTitle' => $this->missionTitle,
                'productName' => $this->productName,
                'reconfirmUrl' => $this->reconfirmUrl,
            ],
        );
    }
}
