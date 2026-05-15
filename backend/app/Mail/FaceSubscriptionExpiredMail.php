<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use App\Models\FaceSubscription;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailables\Content;

final class FaceSubscriptionExpiredMail extends BaseMail
{
    public function __construct(
        public readonly Face $face,
        public readonly FaceSubscription $subscription,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre abonnement Premium annuel a expiré';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.face-subscription-expired',
            with: [
                'faceFirstName' => $this->resolveFaceFirstName(),
                'expiredOnLabel' => $this->formatExpiredOn(),
                'profileUrl' => $this->buildProfileUrl(),
            ],
        );
    }

    private function resolveFaceFirstName(): string
    {
        $prenom = trim((string) $this->face->prenom);
        if ($prenom !== '') {
            return $prenom;
        }
        $displayName = trim((string) $this->face->display_name);

        return $displayName !== '' ? $displayName : 'Face';
    }

    private function formatExpiredOn(): string
    {
        $expiresAt = $this->subscription->expires_at;
        if (! $expiresAt instanceof CarbonInterface) {
            return 'la date de fin prévue';
        }

        return $expiresAt->locale('fr')->translatedFormat('l d F Y');
    }

    private function buildProfileUrl(): string
    {
        $frontendUrl = config('app.frontend_url');
        if (is_string($frontendUrl) && $frontendUrl !== '') {
            return rtrim($frontendUrl, '/').'/face/profile';
        }

        return rtrim((string) config('app.url'), '/').'/face/profile';
    }
}
