<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** Email Face (vouvoiement, D-7.1.e) : produit reçu → le chrono Unboxing démarre, à vous de filmer (ugc-7-3, D-7.3.c). */
final class UgcProductReceivedFaceMail extends BaseMail
{
    public function __construct(
        public readonly string $faceName,
        public readonly string $productName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre produit est arrivé — à vous de filmer 🎬';
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
