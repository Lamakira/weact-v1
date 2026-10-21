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
        public readonly ?string $outcomeNote = null,
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
                'outcomeNote' => $this->outcomeNote,
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
        return \App\Support\Money::format($this->amount);
    }

    private function formatNewBalance(): string
    {
        return \App\Support\Money::format($this->newBalance);
    }

    private function buildWalletUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/producer/wallet';
    }
}
