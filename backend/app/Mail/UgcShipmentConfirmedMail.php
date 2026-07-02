<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Shipment;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Face (tutoiement, D-7.1.e) : le produit a été expédié (tunnel étape 3).
 * View-model fin — le listener résout les strings d'affichage ; les champs
 * intrinsèques (transporteur, n° de suivi) sont lus sur le Shipment porté (D-7.1.c).
 */
final class UgcShipmentConfirmedMail extends BaseMail
{
    public function __construct(
        public readonly Shipment $shipment,
        public readonly string $faceName,
        public readonly string $productName,
        public readonly string $producerName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'Ton produit a été expédié 📦';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-shipment-confirmed',
            with: [
                'faceName' => $this->faceName,
                'productName' => $this->productName,
                'producerName' => $this->producerName,
                'transporteur' => (string) $this->shipment->transporteur,
                'numeroSuivi' => (string) $this->shipment->numero_suivi,
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
