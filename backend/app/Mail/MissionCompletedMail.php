<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use App\Models\Mission;
use Illuminate\Mail\Mailables\Content;

final class MissionCompletedMail extends BaseMail
{
    public function __construct(
        public readonly Face $face,
        public readonly Mission $mission,
        public readonly int $amount,
    ) {}

    protected function subjectLine(): string
    {
        return 'La mission « '.$this->mission->titre.' » est terminée — votre portefeuille a été crédité';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-completed',
            with: [
                'faceFirstName' => $this->resolveFaceFirstName(),
                'missionTitle' => $this->mission->titre,
                'formattedAmount' => $this->formatAmount(),
                'walletUrl' => $this->buildWalletUrl(),
            ],
        );
    }

    private function resolveFaceFirstName(): string
    {
        $prenom = trim((string) $this->face->prenom);

        return $prenom !== '' ? $prenom : $this->face->display_name;
    }

    private function formatAmount(): string
    {
        return number_format($this->amount, 0, ',', ' ').' XOF';
    }

    private function buildWalletUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/face/wallet';
    }
}
