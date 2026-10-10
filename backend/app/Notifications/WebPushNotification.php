<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * One web push to every device of a user. Sent synchronously (notifyNow) from
 * the queued SendWebPush job — never queued itself.
 */
class WebPushNotification extends Notification
{
    /**
     * @param  array{title: string, body: string, url: string, tag: string, ttl: int}  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->payload['title'])
            ->body($this->payload['body'])
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-96.png')
            ->tag($this->payload['tag'])
            ->data(['url' => $this->payload['url']])
            ->options(['TTL' => $this->payload['ttl']]);
    }
}
