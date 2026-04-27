<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\WalletCreditMotif;
use App\Models\Producer;
use Illuminate\Mail\Mailables\Content;

final class WalletCreditedMail extends BaseMail
{
    public function __construct(
        public readonly Producer $producer,
        public readonly int $amount,
        public readonly WalletCreditMotif $motif,
        public readonly int $newBalance,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre portefeuille a été crédité de '.$this->formatAmount();
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet-credited',
            with: [
                'producerName' => $this->resolveProducerName(),
                'formattedAmount' => $this->formatAmount(),
                'motifLabel' => $this->motif->label(),
                'formattedNewBalance' => $this->formatNewBalance(),
                'walletUrl' => $this->buildWalletUrl(),
            ],
        );
    }

    private function resolveProducerName(): string
    {
        $name = trim((string) $this->producer->display_name);

        return $name !== '' ? $name : 'Le Producteur';
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
        return rtrim((string) config('app.frontend_url'), '/').'/producer/wallet';
    }
}
