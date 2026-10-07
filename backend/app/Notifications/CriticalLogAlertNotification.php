<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CriticalLogAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const CONTEXT_MAX_LENGTH = 4000;

    /**
     * @param  array<string, mixed>  $context  Contexte déjà masqué (cf. CriticalLogAlerter).
     */
    public function __construct(
        public readonly string $level,
        public readonly string $logMessage,
        public readonly array $context,
        public readonly string $occurredAt,
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
        $json = json_encode(
            $this->context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ) ?: '{}';

        if (mb_strlen($json) > self::CONTEXT_MAX_LENGTH) {
            $json = mb_substr($json, 0, self::CONTEXT_MAX_LENGTH).' … [tronqué]';
        }

        $mail = (new MailMessage)
            ->subject('[WEACT][CRITIQUE] '.Str::limit($this->logMessage, 80))
            ->greeting('Alerte critique')
            ->line('Niveau : **'.strtoupper($this->level).'**')
            ->line('Message : '.$this->logMessage)
            ->line('Environnement : '.config('app.env'))
            ->line('URL : '.config('app.url'))
            ->line('Date : '.$this->occurredAt);

        if ($this->context !== []) {
            $mail->line('Contexte :')->line("```\n{$json}\n```");
        }

        return $mail->salutation('WEACT - supervision');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
