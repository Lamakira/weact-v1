<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a Google sign-in took over a local account whose address was never
 * verified: the previous password was removed and every session closed.
 */
class GoogleAccountLinkedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Informative only (no token, no link): queued.

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
            ->subject('Votre compte WEACT est désormais lié à Google')
            ->greeting('Bonjour !')
            ->line('Votre compte WEACT est désormais lié à votre compte Google.')
            ->line('Par sécurité, l\'ancien mot de passe a été supprimé et toutes les sessions ont été fermées.')
            ->line('Vous pouvez continuer à vous connecter avec Google. Si vous souhaitez aussi vous connecter sans Google, définissez un nouveau mot de passe depuis votre profil (« Mon compte »).')
            ->line('Si vous n\'êtes pas à l\'origine de cette connexion, veuillez contacter immédiatement notre support à **contact@weact.bj**.')
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
