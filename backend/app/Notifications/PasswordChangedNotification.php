<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

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
            ->subject('Votre mot de passe a été modifié - WEACT')
            ->greeting('Bonjour !')
            ->line('Votre mot de passe WEACT a été modifié avec succès.')
            ->line('Si vous n\'êtes pas à l\'origine de cette modification, veuillez contacter immédiatement notre support à **contact@weact.bj** pour sécuriser votre compte.')
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
