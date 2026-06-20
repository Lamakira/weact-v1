<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** Email Face (tutoiement, D-7.1.e) : produit reçu → le chrono Unboxing démarre, à toi de filmer (ugc-7-3, D-7.3.c). */
final class UgcProductReceivedFaceMail extends BaseMail
{
    public function __construct(
        public readonly string $faceName,
        public readonly string $productName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'Ton produit est arrivé — à toi de filmer 🎬';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-product-received-face',
            with: [
                'faceName' => $this->faceName,
                'productName' => $this->productName,
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
