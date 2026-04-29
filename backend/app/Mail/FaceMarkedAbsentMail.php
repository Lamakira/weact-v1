<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailables\Content;

final class FaceMarkedAbsentMail extends BaseMail
{
    public function __construct(
        public readonly Face $face,
        public readonly Mission $mission,
        public readonly Producer $producer,
        public readonly int $amount,
        public readonly \DateTimeInterface $disputeDeadline,
    ) {}

    protected function subjectLine(): string
    {
        return 'Une absence a été déclarée pour la mission « '.$this->mission->titre.' »';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.face-marked-absent',
            with: [
                'faceFirstName' => $this->resolveFaceFirstName(),
                'missionTitle' => $this->mission->titre,
                'producerName' => $this->resolveProducerName(),
                'shootingDate' => $this->formatShootingDate(),
                'formattedAmount' => $this->formatAmount(),
                'formattedDeadline' => $this->formatDeadline(),
                'missionUrl' => $this->buildMissionUrl(),
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

    private function resolveProducerName(): string
    {
        $displayName = trim((string) $this->producer->display_name);

        return $displayName !== '' ? $displayName : 'Le Producer';
    }

    private function formatShootingDate(): string
    {
        $shootingDate = $this->mission->date_tournage;

        if (! $shootingDate instanceof CarbonInterface) {
            return 'À déterminer';
        }

        $shootingDate->locale('fr');

        return $shootingDate->translatedFormat('l d F Y');
    }

    private function formatAmount(): string
    {
        return number_format($this->amount, 0, ',', ' ').' XOF';
    }

    private function formatDeadline(): string
    {
        $deadline = Carbon::instance($this->disputeDeadline);
        $deadline->locale('fr');

        return $deadline->translatedFormat('l d F Y');
    }

    private function buildMissionUrl(): string
    {
        $frontendUrl = config('app.frontend_url');
        if (is_string($frontendUrl) && $frontendUrl !== '') {
            return rtrim($frontendUrl, '/').'/face/missions/'.$this->mission->uuid;
        }

        $appUrl = config('app.url');
        $baseUrl = is_string($appUrl) ? rtrim($appUrl, '/') : '';

        return $baseUrl.'/face/missions/'.$this->mission->uuid;
    }
}
