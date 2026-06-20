<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Face;
use Illuminate\Mail\Mailables\Content;

/** Email Face (tutoiement, D-7.1.e) : son wallet a été crédité au release escrow (ugc-7-4). */
final class WalletCreditedFaceMail extends BaseMail
{
    public function __construct(
        public readonly Face $face,
        public readonly int $amount,
        public readonly int $newBalance,
    ) {}

    protected function subjectLine(): string
    {
        return 'Ton portefeuille WEACT a été crédité de '.$this->formatAmount().' 🎉';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet-credited-face',
            with: [
                'faceFirstName' => $this->resolveFaceFirstName(),
                'formattedAmount' => $this->formatAmount(),
                'formattedNewBalance' => $this->formatNewBalance(),
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

    private function formatNewBalance(): string
    {
        return number_format($this->newBalance, 0, ',', ' ').' XOF';
    }

    private function buildWalletUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/face/wallet';
    }
}
