<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

/** Copie du contrat d'autorisation d'image signé, PDF joint (Face, Producteur, parent d'une Face mineure). */
final class ImageContractSignedMail extends BaseMail
{
    public function __construct(
        public readonly int $contractId,
        public readonly string $reference,
        public readonly string $pdfPath,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre contrat d\'autorisation d\'image est signé';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.image-contract-signed',
            with: ['reference' => $this->reference],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk((string) config('image_contracts.storage_disk', 'local'), $this->pdfPath)
                ->as('contrat-image-'.$this->reference.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
