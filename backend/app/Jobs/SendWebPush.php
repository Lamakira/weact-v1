<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Push\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Delivers one precomputed web push to all devices of a user.
 * Best effort: failures are logged by the service and never retried.
 */
class SendWebPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array{title: string, body: string, url: string, tag: string, ttl: int}  $payload
     */
    public function __construct(public readonly int $userId, public readonly array $payload) {}

    public function handle(WebPushService $push): void
    {
        $user = User::find($this->userId);

        if ($user !== null) {
            $push->sendToUser($user, $this->payload);
        }
    }
}
