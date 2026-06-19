<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Deliverable;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Face (tutoiement, D-7.1.e) : une retouche est demandée sur ton livrable,
 * avec le motif (review_note). La fenêtre d'upload du même kind est rouverte
 * (tunnel étape 5/6). Le motif est lu sur le Deliverable porté (D-7.1.c).
 */
final class UgcDeliverableRetoucheRequestedMail extends BaseMail
{
    public function __construct(
        public readonly Deliverable $deliverable,
        public readonly string $faceName,
        public readonly string $productName,
        public readonly string $dealUrl,
    ) {}

    protected function subjectLine(): string
    {
        return 'Retouche demandée sur ton livrable';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ugc-deliverable-retouche-requested',
            with: [
                'faceName' => $this->faceName,
                'productName' => $this->productName,
                'kindLabel' => $this->deliverable->kind->label(),
                'reviewNote' => (string) $this->deliverable->review_note,
                'dealUrl' => $this->dealUrl,
            ],
        );
    }
}
