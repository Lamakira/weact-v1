<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Events\ConversationUpdated;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lecture et temps réel des chats de booking dans la zone « Messages ».
 *
 * Calqué sur ConversationRealtime : même événement utilisateur (ConversationUpdated, avec
 * kind = 'booking') pour que le badge Messages se mette à jour en direct. Les diffusions
 * sont protégées : une panne du broadcaster ne casse jamais la requête HTTP.
 */
class BookingChatRealtime
{
    /**
     * Après l'envoi d'un message : une mise à jour de liste par partie (chacune avec son total).
     */
    public function messageSent(Booking $booking, BookingMessage $message): void
    {
        $this->safely(fn () => $this->dispatchListUpdates($booking, $message));
    }

    /**
     * Marque lus les messages de l'autre partie pour $reader.
     *
     * Ne diffuse (mise à jour pour les autres appareils du lecteur) que si quelque chose a changé.
     *
     * @return int Nombre de messages marqués lus.
     */
    public function markRead(Booking $booking, User $reader): int
    {
        // Comme les conversations : un utilisateur bloqué par le mur de vérification d'e-mail ne marque rien lu.
        if (! $reader->hasVerifiedEmail()) {
            return 0;
        }

        $marked = $booking->messages()
            ->reorder()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $reader->id)
            ->update(['read_at' => now()]);

        if ($marked === 0) {
            return 0;
        }

        $this->safely(function () use ($booking, $reader): void {
            /** @var BookingMessage|null $latest */
            $latest = $booking->messages()->reorder()->latest('id')->with('sender.userable')->first();

            if ($latest) {
                $this->dispatchListUpdates($booking, $latest, [$reader->id]);
            }
        });

        return $marked;
    }

    /**
     * @param  list<int>|null  $onlyUserIds
     */
    private function dispatchListUpdates(Booking $booking, BookingMessage $latest, ?array $onlyUserIds = null): void
    {
        $latest->loadMissing('sender.userable');
        $counter = app(ConversationUnreadCounter::class);

        $excerpt = [
            'id' => $latest->id,
            'content' => Str::limit($latest->content, 50),
            'sender_id' => $latest->sender_id,
            'sender_name' => data_get($latest, 'sender.userable.display_name', 'Inconnu'),
            'created_at' => $latest->created_at->toIso8601String(),
        ];

        foreach (array_unique([$booking->face_id, $booking->producer_id]) as $userId) {
            if ($onlyUserIds !== null && ! in_array($userId, $onlyUserIds, true)) {
                continue;
            }

            $participant = User::find($userId);

            if ($participant === null) {
                continue;
            }

            $unread = $booking->messages()
                ->reorder()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $userId)
                ->count();

            broadcast(new ConversationUpdated(
                $userId,
                $booking->uuid,
                $excerpt,
                $unread,
                $latest->created_at->toIso8601String(),
                $counter->countFor($participant),
                'booking',
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
