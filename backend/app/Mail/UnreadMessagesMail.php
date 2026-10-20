<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * Email (vouvoiement) : des messages restent non lus dans un fil (conversation de mission ou
 * chat de booking). Volontairement SANS le texte des messages : anti-contournement (aucune
 * coordonnée ne doit transiter par e-mail), il pousse seulement vers la messagerie.
 */
final class UnreadMessagesMail extends BaseMail
{
    public function __construct(
        public readonly string $senderName,
        public readonly int $unreadCount,
        public readonly string $contextLabel,
        public readonly string $threadUrl,
    ) {}

    protected function subjectLine(): string
    {
        return $this->unreadCount > 1
            ? 'Vous avez des messages non lus sur WEACT'
            : 'Vous avez un message non lu sur WEACT';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.unread-messages',
            with: [
                'senderName' => $this->senderName,
                'unreadCount' => $this->unreadCount,
                'contextLabel' => $this->contextLabel,
                'threadUrl' => $this->threadUrl,
            ],
        );
    }
}
