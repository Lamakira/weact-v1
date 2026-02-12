<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailChangeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $newEmail,
        private readonly User $user,
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
        $confirmationUrl = $this->confirmationUrl($notifiable);

        return (new MailMessage)
            ->subject('Confirmez votre nouvelle adresse email - WEACT')
            ->greeting('Bonjour !')
            ->line('Vous avez demandé à changer votre adresse email sur WEACT.')
            ->line("Veuillez cliquer sur le bouton ci-dessous pour confirmer votre nouvelle adresse email : **{$this->newEmail}**")
            ->action('Confirmer mon nouvel email', $confirmationUrl)
            ->line('Ce lien expirera dans 60 minutes.')
            ->line("Si vous n'avez pas demandé ce changement, ignorez cet email.")
            ->salutation('Cordialement, L\'équipe WEACT');
    }

    protected function confirmationUrl(object $notifiable): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        $signedUrl = URL::temporarySignedRoute(
            'email-change.confirm',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $this->user->getKey(),
                'hash' => sha1($this->newEmail),
            ]
        );

        $parsedUrl = parse_url($signedUrl);
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';

        preg_match('/\/email\/change\/confirm\/(\d+)\/([a-f0-9]+)/', $path, $matches);
        $id = $matches[1] ?? $this->user->getKey();
        $hash = $matches[2] ?? sha1($this->newEmail);

        return $frontendUrl . '/email-change/confirm/' . $id . '/' . $hash . '?' . $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
