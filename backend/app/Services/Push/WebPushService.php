<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Jobs\SendWebPush;
use App\Models\Face;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Notifications\WebPushNotification;
use App\Support\Push\PushAllowlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Web push (VAPID) for the PWA: in-app notifications in the allowlist and
 * throttled new-message alerts. Everything here is best effort and silent when
 * VAPID keys are missing.
 */
class WebPushService
{
    /** At most one message push per conversation and recipient within this window. */
    public const MESSAGE_THROTTLE_SECONDS = 300;

    private const BODY_LIMIT = 140;

    public function isEnabled(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        return $this->isEnabled() ? (string) config('webpush.vapid.public_key') : null;
    }

    /**
     * Queue a push for an allowlisted in-app notification (no-op otherwise).
     */
    public function queueForNotification(Notification $notification): void
    {
        if (! $this->isEnabled() || ! PushAllowlist::isAllowed($notification->type)) {
            return;
        }

        $data = $notification->data;
        $url = $this->safeUrl($data['url'] ?? null);

        SendWebPush::dispatch($notification->user_id, [
            'title' => (string) PushAllowlist::titleFor($notification->type),
            'body' => Str::limit(trim((string) ($data['message'] ?? '')), self::BODY_LIMIT - 1, '…'),
            'url' => $url,
            'tag' => $notification->type.':'.md5($url),
            'ttl' => 86400,
        ])->afterCommit();
    }

    /**
     * Queue a push to the other participant of a conversation for a new message,
     * throttled to one push per conversation per recipient every 5 minutes.
     */
    public function queueForMessage(Message $message, User $recipient, string $conversationUuid): void
    {
        if (! $this->isEnabled() || ! $recipient->pushSubscriptions()->exists()) {
            return;
        }

        if (! Cache::add("push:message:{$conversationUuid}:{$recipient->id}", 1, self::MESSAGE_THROTTLE_SECONDS)) {
            return;
        }

        $area = $recipient->userable_type === Face::class ? 'face' : 'producer';

        SendWebPush::dispatch($recipient->id, [
            'title' => 'Nouveau message',
            'body' => Str::limit($this->senderFirstName($message).': '.trim($message->content), self::BODY_LIMIT - 1, '…'),
            'url' => "/{$area}/conversations/{$conversationUuid}",
            'tag' => "conversation:{$conversationUuid}",
            'ttl' => 3600,
        ])->afterCommit();
    }

    /**
     * Send a payload to every device of the user. Never throws.
     *
     * @param  array{title: string, body: string, url: string, tag: string, ttl: int}  $payload
     */
    public function sendToUser(User $user, array $payload): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            if (! $user->pushSubscriptions()->exists()) {
                return;
            }

            $user->notifyNow(new WebPushNotification($payload));
        } catch (Throwable $e) {
            Log::warning('Web push failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function senderFirstName(Message $message): string
    {
        $message->loadMissing('sender.userable');
        $profile = $message->sender?->userable;

        $name = match (true) {
            $profile instanceof Face => $profile->prenom,
            $profile instanceof Producer => $profile->first_name,
            default => null,
        };

        return filled($name) ? (string) $name : (string) data_get($profile, 'display_name', 'WeAct');
    }

    /**
     * Only same-origin frontend paths are pushed; anything else falls back to the home page.
     */
    private function safeUrl(mixed $url): string
    {
        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/';
    }
}
