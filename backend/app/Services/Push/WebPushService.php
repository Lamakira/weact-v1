<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Jobs\SendWebPush;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Notifications\WebPushNotification;
use App\Support\Push\PushAllowlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\PushSubscription;
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
        if (! $this->isEnabled()
            || ! PushAllowlist::isAllowed($notification->type)
            || ! PushSubscription::where('subscribable_type', User::class)->where('subscribable_id', $notification->user_id)->exists()) {
            return;
        }

        $data = $notification->data;
        $url = $this->safeUrl($data['url'] ?? null);

        SendWebPush::dispatch($notification->user_id, [
            'title' => (string) PushAllowlist::titleFor($notification->type),
            'body' => Str::limit(trim((string) ($data['message'] ?? '')), self::BODY_LIMIT - 1, '…'),
            'url' => $url,
            'tag' => $notification->type.':'.$notification->uuid,
            'ttl' => 86400,
        ])->afterCommit();
    }

    /**
     * Queue a push for a new chat message ($scope: 'conversation' or 'booking'),
     * throttled to one push per chat and recipient every 5 minutes. The tag is per
     * chat, so several messages collapse into one notification.
     */
    public function queueForChatMessage(User $sender, string $content, User $recipient, string $scope, string $uuid): void
    {
        if (! $this->isEnabled() || ! $recipient->pushSubscriptions()->exists()) {
            return;
        }

        if (! Cache::add("push:message:{$scope}:{$uuid}:{$recipient->id}", 1, self::MESSAGE_THROTTLE_SECONDS)) {
            return;
        }

        $area = $recipient->userable_type === Face::class ? 'face' : 'producer';
        $path = $scope === 'booking' ? 'bookings' : 'conversations';

        SendWebPush::dispatch($recipient->id, [
            'title' => 'Nouveau message',
            'body' => Str::limit($this->senderFirstName($sender).': '.trim($content), self::BODY_LIMIT - 1, '…'),
            'url' => "/{$area}/{$path}/{$uuid}",
            'tag' => "{$scope}:{$uuid}",
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

    private function senderFirstName(User $sender): string
    {
        $sender->loadMissing('userable');
        $profile = $sender->userable;

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
