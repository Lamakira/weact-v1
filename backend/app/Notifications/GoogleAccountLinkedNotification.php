<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on every NEW link of a Google identity to an existing local account.
 * When the address had never been verified ($accountSecured), the previous
 * password was removed and every session closed; otherwise credentials are untouched.
 */
class GoogleAccountLinkedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Informative only (no token, no link): queued.

    public function __construct(public readonly bool $accountSecured = true) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Votre compte WEACT est désormais lié à Google')
            ->greeting('Bonjour !')
            ->line('Votre compte WEACT est désormais lié à votre compte Google.');

        if ($notifiable instanceof User) {
            $mail->line('Compte concerné : '.$notifiable->email);
        }

        if ($this->accountSecured) {
            $mail->line('Par sécurité, l\'ancien mot de passe a été supprimé et toutes les sessions ont été fermées.')
                ->line('Vous pouvez continuer à vous connecter avec Google. Si vous souhaitez aussi vous connecter sans Google, définissez un nouveau mot de passe depuis votre profil (« Mon compte »).');
        } else {
            $mail->line('Votre mot de passe et vos sessions en cours ne sont pas modifiés : vous pouvez vous connecter avec votre mot de passe ou avec Google.');
        }

        return $mail
            ->line('Si vous n\'êtes pas à l\'origine de cette connexion, veuillez contacter immédiatement notre support à **contact@weact.bj** et changer votre mot de passe.')
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
