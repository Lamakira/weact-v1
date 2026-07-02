<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** Email Face (tutoiement, D-7.1.e) : la commission est réglée → accepte le deal pour recevoir le produit (ugc-7-3, D-7.3.d). */
final class UgcCommissionPaidMail extends BaseMail
{
    public function __construct(
        public readonly string $faceName,
        public readonly string $producerName,
        public readonly string $productName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return "C'est réglé — accepte ton deal UGC 🎁";
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-commission-paid',
            with: [
                'faceName' => $this->faceName,
                'producerName' => $this->producerName,
                'productName' => $this->productName,
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
