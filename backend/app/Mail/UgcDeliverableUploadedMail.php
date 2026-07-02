<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Deliverable;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Producteur (vouvoiement, D-7.1.e) : une nouvelle vidéo UGC a été déposée
 * et attend sa validation (tunnel étape 5). Le type de livrable (Unboxing/Avis)
 * est lu sur le Deliverable porté (D-7.1.c).
 */
final class UgcDeliverableUploadedMail extends BaseMail
{
    public function __construct(
        public readonly Deliverable $deliverable,
        public readonly string $producerName,
        public readonly string $productName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'Une nouvelle vidéo UGC à valider';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-deliverable-uploaded',
            with: [
                'producerName' => $this->producerName,
                'productName' => $this->productName,
                'kindLabel' => $this->deliverable->kind->label(),
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
