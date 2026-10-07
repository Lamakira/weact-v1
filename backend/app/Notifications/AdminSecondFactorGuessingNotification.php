<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent after several wrong second-factor codes in a row following a CORRECT
 * password: someone knows the admin's password and is guessing the code.
 */
class AdminSecondFactorGuessingNotification extends Notification implements ShouldQueue
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
            ->subject('Tentatives de connexion suspectes — WEACT Administration')
            ->greeting('Bonjour,')
            ->line('Quelqu\'un connaît votre mot de passe WEACT et a saisi plusieurs codes de vérification incorrects pour accéder à votre compte administrateur.')
            ->line('Votre double authentification a bloqué l\'accès, mais votre mot de passe est compromis : changez-le immédiatement, puis prévenez un super-administrateur.')
            ->salutation('L\'équipe WEACT');
    }
}
