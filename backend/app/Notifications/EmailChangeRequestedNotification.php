<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $newEmail,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Demande de changement d\'email - WEACT')
            ->greeting('Bonjour !')
            ->line('Une demande de changement d\'adresse email a été effectuée sur votre compte WEACT.')
            ->line("La nouvelle adresse demandée est : **{$this->newEmail}**")
            ->line('Si vous êtes à l\'origine de cette demande, un email de confirmation a été envoyé à la nouvelle adresse.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, nous vous recommandons de changer votre mot de passe immédiatement.')
            ->salutation('Cordialement, L\'équipe WEACT');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
