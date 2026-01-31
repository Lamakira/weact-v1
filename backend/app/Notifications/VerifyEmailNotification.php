<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Vérifiez votre adresse email - WEACT')
            ->greeting('Bienvenue sur WEACT !')
            ->line('Merci de vous être inscrit. Veuillez cliquer sur le bouton ci-dessous pour vérifier votre adresse email.')
            ->action('Vérifier mon email', $verificationUrl)
            ->line('Ce lien expirera dans 60 minutes.')
            ->line("Si vous n'avez pas créé de compte, aucune action n'est requise.")
            ->salutation('Cordialement, L\'équipe WEACT');
    }

    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl(object $notifiable): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        // Generate a signed URL pointing to the backend API
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Extract the path and query parameters from the signed URL
        $parsedUrl = parse_url($signedUrl);
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';

        // Extract the id and hash from the path
        // Path format: /api/v1/auth/email/verify/{id}/{hash}
        preg_match('/\/email\/verify\/(\d+)\/([a-f0-9]+)/', $path, $matches);
        $id = $matches[1] ?? $notifiable->getKey();
        $hash = $matches[2] ?? sha1($notifiable->getEmailForVerification());

        // Build the frontend URL with all necessary parameters
        return $frontendUrl . '/verify-email/' . $id . '/' . $hash . '?' . $query;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
