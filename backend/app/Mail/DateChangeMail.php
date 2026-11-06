<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * Email générique du changement de date (mission et booking) : objet, titre, lignes de texte
 * et un bouton. Vouvoiement, texte neutre en genre.
 */
final class DateChangeMail extends BaseMail
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $subjectText,
        public readonly string $heading,
        public readonly array $lines,
        public readonly string $ctaLabel,
        public readonly string $ctaUrl,
    ) {}

    protected function subjectLine(): string
    {
        return $this->subjectText;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.date-change',
            with: [
                'heading' => $this->heading,
                'lines' => $this->lines,
                'ctaLabel' => $this->ctaLabel,
                'ctaUrl' => $this->ctaUrl,
            ],
        );
    }
}
