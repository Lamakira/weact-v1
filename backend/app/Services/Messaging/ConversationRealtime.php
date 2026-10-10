<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Events\ConversationMessageSent;
use App\Events\ConversationMessagesRead;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\User;
use App\Services\Push\WebPushService;
use Illuminate\Support\Str;
use Throwable;

/**
 * Real-time side of the Messages area (Face <-> Producer conversations).
 *
 * Broadcasts are queued (ShouldBroadcast) and wrapped so a broadcaster outage
 * can never fail the HTTP request.
 */
class ConversationRealtime
{
    /**
     * Broadcast a freshly stored message: thread channel + one list update per participant.
     */
    public function messageSent(Conversation $conversation, Message $message): void
    {
        $message->loadMissing('sender.userable');

        $this->safely(function () use ($conversation, $message): void {
            broadcast(new ConversationMessageSent($message, $conversation->uuid))->toOthers();

            $this->dispatchListUpdates($conversation, $message);
        });

        // Throttled web push to the other participant; independent of the broadcast outcome.
        $this->safely(function () use ($conversation, $message): void {
            $conversation->loadMissing('candidature.face.user', 'candidature.mission.producer.user');

            $participants = array_filter([
                $conversation->candidature?->face?->user,
                $conversation->candidature?->mission?->producer?->user,
            ]);

            foreach ($participants as $participant) {
                if ($participant->id !== $message->sender_id) {
                    app(WebPushService::class)->queueForChatMessage($message->sender, $message->content, $participant, 'conversation', $conversation->uuid);
                }
            }
        });
    }

    /**
     * Mark the other participant's unread messages as read for $reader.
     *
     * Broadcasts a read receipt (and a list update for the reader's other
     * devices) only when something actually changed.
     *
     * @return int Number of messages marked as read.
     */
    public function markRead(Conversation $conversation, User $reader): int
    {
        // A user blocked by the email-verification wall must not mark anything as read
        if (! $reader->hasVerifiedEmail()) {
            return 0;
        }

        $ids = $conversation->messages()
            ->reorder()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $reader->id)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $now = now();

        $marked = Message::whereIn('id', $ids)->whereNull('read_at')->update(['read_at' => $now]);

        if ($marked === 0) {
            return 0;
        }

        $this->safely(function () use ($conversation, $reader, $ids, $now): void {
            broadcast(new ConversationMessagesRead(
                $conversation->uuid,
                $reader->userable_type === Face::class ? 'face' : 'producer',
                $reader->id,
                $now->toIso8601String(),
                (int) $ids->max(),
            ))->toOthers();

            /** @var Message|null $latest */
            $latest = $conversation->messages()->reorder()->latest('id')->with('sender.userable')->first();

            if ($latest) {
                $this->dispatchListUpdates($conversation, $latest, [$reader->id]);
            }
        });

        return $marked;
    }

    /**
     * One ConversationUpdated per participant, each with its own unread_count.
     *
     * @param  list<int>|null  $onlyUserIds
     */
    private function dispatchListUpdates(Conversation $conversation, Message $latest, ?array $onlyUserIds = null): void
    {
        $conversation->loadMissing('candidature.face.user', 'candidature.mission.producer.user');

        $participants = array_filter([
            $conversation->candidature?->face?->user,
            $conversation->candidature?->mission?->producer?->user,
        ]);
        $counter = app(ConversationUnreadCounter::class);

        $excerpt = [
            'id' => $latest->id,
            'content' => Str::limit($latest->content, 50),
            'sender_id' => $latest->sender_id,
            'sender_name' => data_get($latest, 'sender.userable.display_name', 'Inconnu'),
            'created_at' => $latest->created_at->toIso8601String(),
        ];

        foreach ($participants as $participant) {
            $userId = $participant->id;

            if ($onlyUserIds !== null && ! in_array($userId, $onlyUserIds, true)) {
                continue;
            }

            $unread = $conversation->messages()
                ->reorder()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $userId)
                ->count();

            broadcast(new ConversationUpdated(
                $userId,
                $conversation->uuid,
                $excerpt,
                $unread,
                $latest->created_at->toIso8601String(),
                $counter->countFor($participant),
            ))->toOthers();
        }
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
