<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** Email Face (tutoiement) : sa candidature UGC n'a pas été retenue → explorer d'autres missions (ugc-8-2, D-8.2.g, FR6). */
final class CandidatureRefusedMail extends BaseMail
{
    public function __construct(
        public readonly string $faceName,
        public readonly string $missionTitle,
        public readonly string $browseUrl,
    ) {}

    protected function subjectLine(): string
    {
        return "Votre candidature n'a pas été retenue";
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidature-refused',
            with: [
                'faceName' => $this->faceName,
                'missionTitle' => $this->missionTitle,
                'browseUrl' => $this->browseUrl,
            ],
        );
    }
}
