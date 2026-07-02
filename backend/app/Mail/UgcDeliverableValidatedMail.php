<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Deliverable;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Face (tutoiement, D-7.1.e) : ton livrable a été validé par le Producteur
 * (tunnel étape 5/6).
 */
final class UgcDeliverableValidatedMail extends BaseMail
{
    public function __construct(
        public readonly Deliverable $deliverable,
        public readonly string $faceName,
        public readonly string $productName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'Ton livrable est validé ✅';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-deliverable-validated',
            with: [
                'faceName' => $this->faceName,
                'productName' => $this->productName,
                'kindLabel' => $this->deliverable->kind->label(),
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
