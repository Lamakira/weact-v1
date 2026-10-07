<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security alert sent to an admin whenever the two-factor setup of THEIR account
 * changes (so a silent takeover attempt does not go unnoticed).
 */
class AdminTwoFactorChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const EVENT_ENABLED = 'enabled';

    public const EVENT_DISABLED = 'disabled';

    public const EVENT_RECOVERY_CODES_REGENERATED = 'recovery_codes_regenerated';

    public const EVENT_RESET = 'reset';

    public function __construct(
        public readonly string $event
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
        [$subject, $line] = match ($this->event) {
            self::EVENT_ENABLED => [
                'Double authentification activée — WEACT Administration',
                'La double authentification vient d\'être activée sur votre compte administrateur.',
            ],
            self::EVENT_DISABLED => [
                'Double authentification désactivée — WEACT Administration',
                'La double authentification vient d\'être désactivée sur votre compte administrateur.',
            ],
            self::EVENT_RECOVERY_CODES_REGENERATED => [
                'Nouveaux codes de secours générés — WEACT Administration',
                'De nouveaux codes de secours viennent d\'être générés ; les précédents ne fonctionnent plus.',
            ],
            default => [
                'Double authentification réinitialisée — WEACT Administration',
                'La double authentification de votre compte administrateur a été réinitialisée par un super-administrateur. Vos sessions ont été fermées : vous devrez la reconfigurer à votre prochaine connexion.',
            ],
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Bonjour,')
            ->line($line)
            ->line('Si vous n\'êtes pas à l\'origine de cette modification, changez immédiatement votre mot de passe et contactez un super-administrateur.')
            ->salutation('L\'équipe WEACT');
    }
}
