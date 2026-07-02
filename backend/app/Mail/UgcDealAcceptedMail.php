<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement, D-7.1.e) : la Face a accepté → expédier le produit (ugc-7-3). */
final class UgcDealAcceptedMail extends BaseMail
{
    public function __construct(
        public readonly string $producerName,
        public readonly string $faceName,
        public readonly string $productName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre deal UGC est accepté — expédiez le produit 🚚';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-deal-accepted',
            with: [
                'producerName' => $this->producerName,
                'faceName' => $this->faceName,
                'productName' => $this->productName,
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
