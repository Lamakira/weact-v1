<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Shipment;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Producteur (vouvoiement, D-7.1.e) : la Face a confirmé la réception du
 * produit, le chrono Unboxing démarre (tunnel étape 4). Le nom du destinataire
 * est lu sur le Shipment porté (D-7.1.c).
 */
final class UgcProductReceivedMail extends BaseMail
{
    public function __construct(
        public readonly Shipment $shipment,
        public readonly string $producerName,
        public readonly string $productName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'La Face a confirmé la réception du produit';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-product-received',
            with: [
                'producerName' => $this->producerName,
                'productName' => $this->productName,
                'destinataireNom' => (string) $this->shipment->destinataire_nom,
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
