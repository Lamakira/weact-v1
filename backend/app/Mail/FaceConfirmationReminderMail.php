<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

/** Email Face (vouvoiement) : dernière ligne droite pour confirmer sa participation à une mission payée. */
final class FaceConfirmationReminderMail extends BaseMail
{
    public function __construct(
        public readonly Face $face,
        public readonly Mission $mission,
        public readonly string $deadline,
    ) {}

    protected function subjectLine(): string
    {
        return 'Confirmez votre participation à « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        $prenom = trim((string) $this->face->prenom);

        return new Content(
            view: 'emails.face-confirmation-reminder',
            with: [
                'faceFirstName' => $prenom !== '' ? $prenom : $this->face->display_name,
                'missionTitle' => $this->mission->titre,
                'deadline' => $this->deadline,
                'confirmUrl' => rtrim((string) config('app.frontend_url'), '/')."/face/missions/{$this->mission->uuid}",
            ],
        );
    }
}
