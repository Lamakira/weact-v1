<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Liste unifiée « Messages » : conversations de mission + chats de booking lisibles
 * (BookingPolicy::viewMessages), triés ensemble par date du dernier message.
 *
 * Pagination juste sur l'ensemble fusionné : une seule requête UNION ALL ne sélectionne que
 * (kind, id, last_at) et c'est CETTE requête qui est paginée. Les modèles de la page sont
 * ensuite chargés en lot (une requête par type, eager loading) puis réordonnés : pas de N+1.
 *
 * Un fil de booking n'apparaît que s'il contient au moins un message.
 */
class MessageThreadLister
{
    /**
     * @return LengthAwarePaginator<int, Conversation|Booking> items = modèles dans l'ordre de la page
     */
    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $page = $this->threadIds($user)->paginate($perPage);

        $rows = collect($page->items());
        $conversations = $this->loadConversations($user, $rows->where('kind', 'mission')->pluck('id')->all());
        $bookings = $this->loadBookings($user, $rows->where('kind', 'booking')->pluck('id')->all());

        $items = $rows
            ->map(fn (object $row) => $row->kind === 'mission' ? $conversations->get($row->id) : $bookings->get($row->id))
            ->filter()
            ->values();

        return new LengthAwarePaginator($items, $page->total(), $page->perPage(), $page->currentPage(), [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }

    private function threadIds(User $user): \Illuminate\Database\Query\Builder
    {
        $conversations = DB::table('conversations')
            ->join('candidatures', 'candidatures.id', '=', 'conversations.candidature_id')
            ->selectRaw("'mission' as kind, conversations.id as id, COALESCE((SELECT MAX(created_at) FROM messages WHERE conversation_id = conversations.id), conversations.updated_at) as last_at");

        if ($user->userable_type === Face::class) {
            $conversations->where('candidatures.face_id', $user->userable_id);
        } elseif ($user->userable_type === Producer::class) {
            $conversations->join('missions', 'missions.id', '=', 'candidatures.mission_id')
                ->where('missions.producer_id', $user->userable_id);
        } else {
            $conversations->whereRaw('1 = 0');
        }

        $bookings = Booking::query()
            ->chatReadableBy($user)
            ->whereHas('messages')
            ->selectRaw("'booking' as kind, bookings.id as id, (SELECT MAX(created_at) FROM booking_messages WHERE booking_id = bookings.id) as last_at")
            ->toBase();

        return DB::query()
            ->fromSub($conversations->unionAll($bookings), 'threads')
            ->orderByDesc('last_at')
            ->orderBy('kind')
            ->orderByDesc('id');
    }

    /**
     * @param  list<int|string>  $ids
     * @return \Illuminate\Support\Collection<int, Conversation>
     */
    private function loadConversations(User $user, array $ids): \Illuminate\Support\Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Conversation::query()
            ->whereIn('conversations.id', $ids)
            ->with([
                'candidature.mission.producer',
                'candidature.face',
                'candidature.paymentEntry.missionPayment',
                'latestMessage.sender.userable',
            ])
            ->withUnreadCountFor($user)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<int|string>  $ids
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    private function loadBookings(User $user, array $ids): \Illuminate\Support\Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Booking::query()
            ->whereIn('bookings.id', $ids)
            ->with(['face.userable', 'producer.userable', 'latestMessage.sender.userable'])
            ->withUnreadCountFor($user)
            ->get()
            ->keyBy('id');
    }
}
