<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FaceExclusivitySlotFreedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $faceId,
        public readonly string $faceName,
        public readonly string $currentTierLabel,
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
            ->subject('Une place d\'exclusivité Élite est libre')
            ->greeting('Une place d\'exclusivité Élite est libre')
            ->line("{$this->faceName} a perdu le palier Élite : son exclusivité a été retirée automatiquement.")
            ->line("Palier actuel : {$this->currentTierLabel}.")
            ->line('Vous pouvez accorder cette place à une autre Face Élite depuis le back-office.')
            ->salutation('WEACT - supervision');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
