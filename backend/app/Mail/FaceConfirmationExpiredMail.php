<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/** Email Face (vouvoiement) : participation non confirmée dans les délais, place libérée. */
final class FaceConfirmationExpiredMail extends BaseMail
{
    public function __construct(
        public readonly Face $face,
        public readonly Mission $mission,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre place sur « '.$this->mission->titre.' » a été libérée';
    }

    public function content(): Content
    {
        $prenom = trim((string) $this->face->prenom);

        return new Content(
            view: 'emails.face-confirmation-expired',
            with: [
                'faceFirstName' => $prenom !== '' ? $prenom : $this->face->display_name,
                'missionTitle' => $this->mission->titre,
                'missionsUrl' => rtrim((string) config('app.frontend_url'), '/').'/face/candidatures',
            ],
        );
    }
}
