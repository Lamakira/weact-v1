<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Délai de réponse des Faces : source unique des règles (tournage proche, e-mail « non lu »,
 * attente en cours, absence de réponse, délai habituel). Consommé par la discussion Producteur,
 * l'e-mail « message non lu » et la page admin « Faces à contacter ».
 */
final class FaceResponsiveness
{
    /** Délai de l'e-mail « message non lu » en temps normal. */
    public const UNREAD_EMAIL_AFTER_MINUTES = 60;

    /** Délai de l'e-mail « message non lu » quand le tournage est proche. */
    public const URGENT_UNREAD_EMAIL_AFTER_MINUTES = 15;

    /** Tournage proche : le début de tournage tombe dans les prochaines 48 h (ou a commencé il y a moins de 24 h). */
    public const URGENT_WINDOW_HOURS = 48;

    /** Sans réponse de la Face depuis ce délai : la Face est signalée. */
    public const OVERDUE_HOURS = 12;

    /** Idem quand le tournage est proche. */
    public const URGENT_OVERDUE_HOURS = 2;

    /** Fenêtre glissante du délai habituel. */
    public const HISTORY_DAYS = 90;

    /** Nombre minimal de fils avec au moins un délai pour afficher un délai habituel. */
    public const MIN_THREADS = 3;

    /** Durée de mise en cache du délai habituel (secondes). */
    private const CACHE_TTL_SECONDS = 3600;

    /** Tournage proche : début non null, pas commencé depuis plus de 24 h, et dans les 48 h à venir. */
    public function isUrgent(?CarbonInterface $shootStart): bool
    {
        if ($shootStart === null) {
            return false;
        }

        $now = now();

        return $now->lt($shootStart->copy()->addHours(24))
            && $shootStart->lte($now->copy()->addHours(self::URGENT_WINDOW_HOURS));
    }

    public function unreadEmailAfterMinutes(?CarbonInterface $shootStart): int
    {
        return $this->isUrgent($shootStart)
            ? self::URGENT_UNREAD_EMAIL_AFTER_MINUTES
            : self::UNREAD_EMAIL_AFTER_MINUTES;
    }

    /**
     * Début de l'attente en cours : premier message d'un autre expéditeur que la Face envoyé après
     * son dernier message. Null si le dernier message est de la Face ou s'il n'y en a aucun.
     *
     * @param  iterable<object{sender_id: int, created_at: CarbonInterface|null}>  $messages  triés par date croissante
     */
    public function pendingSince(iterable $messages, int $faceUserId): ?CarbonInterface
    {
        $pending = null;

        foreach ($messages as $message) {
            if ((int) $message->sender_id === $faceUserId) {
                $pending = null;
            } elseif ($pending === null) {
                $pending = $message->created_at;
            }
        }

        return $pending;
    }

    public function isOverdue(?CarbonInterface $pendingSince, bool $urgent): bool
    {
        if ($pendingSince === null) {
            return false;
        }

        $hours = $urgent ? self::URGENT_OVERDUE_HOURS : self::OVERDUE_HOURS;

        return $pendingSince->lte(now()->subHours($hours));
    }

    /**
     * Médiane (minutes) des délais de réponse de la Face sur 90 jours, null sous 3 fils exploitables.
     * Deux requêtes au plus (messages de mission, messages de booking), résultat mis en cache 1 h.
     */
    public function typicalResponseMinutes(User $faceUser): ?int
    {
        $value = Cache::remember(
            "face_response:typical:{$faceUser->id}",
            self::CACHE_TTL_SECONDS,
            fn (): int => $this->computeTypicalResponseMinutes($faceUser) ?? -1,
        );

        return $value < 0 ? null : $value;
    }

    private function computeTypicalResponseMinutes(User $faceUser): ?int
    {
        $since = now()->subDays(self::HISTORY_DAYS);

        $missionGroups = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('candidatures', 'candidatures.id', '=', 'conversations.candidature_id')
            ->where('candidatures.face_id', $faceUser->userable_type === Face::class ? $faceUser->userable_id : 0)
            ->where('messages.created_at', '>=', $since)
            ->orderBy('messages.conversation_id')
            ->orderBy('messages.created_at')
            ->orderBy('messages.id')
            ->get(['messages.conversation_id as thread_id', 'messages.sender_id', 'messages.created_at'])
            ->groupBy('thread_id');

        $bookingGroups = BookingMessage::query()
            ->join('bookings', 'bookings.id', '=', 'booking_messages.booking_id')
            ->where('bookings.face_id', $faceUser->id)
            ->where('booking_messages.created_at', '>=', $since)
            ->orderBy('booking_messages.booking_id')
            ->orderBy('booking_messages.created_at')
            ->orderBy('booking_messages.id')
            ->get(['booking_messages.booking_id as thread_id', 'booking_messages.sender_id', 'booking_messages.created_at'])
            ->groupBy('thread_id');

        $delays = [];
        $threads = 0;

        foreach ([$missionGroups, $bookingGroups] as $groups) {
            foreach ($groups as $messages) {
                $threadDelays = $this->threadDelays($messages, $faceUser->id);
                if ($threadDelays !== []) {
                    $threads++;
                    array_push($delays, ...$threadDelays);
                }
            }
        }

        if ($threads < self::MIN_THREADS) {
            return null;
        }

        sort($delays);
        $count = count($delays);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1 ? $delays[$middle] : ($delays[$middle - 1] + $delays[$middle]) / 2;

        return (int) round($median);
    }

    /**
     * Délais (minutes) d'un fil trié : un message non-Face ouvre une attente (si aucune n'est ouverte),
     * le message suivant de la Face la ferme.
     *
     * @param  iterable<object{sender_id: int, created_at: CarbonInterface|null}>  $messages
     * @return list<float>
     */
    private function threadDelays(iterable $messages, int $faceUserId): array
    {
        $delays = [];
        $open = null;

        foreach ($messages as $message) {
            if ((int) $message->sender_id === $faceUserId) {
                if ($open !== null) {
                    $delays[] = max(0, $message->created_at->getTimestamp() - $open->getTimestamp()) / 60;
                    $open = null;
                }
            } elseif ($open === null) {
                $open = $message->created_at;
            }
        }

        return $delays;
    }

    /**
     * Payload `data` de GET /bookings/{booking}/face-response.
     *
     * @return array{face_first_name: string, unread_email_after_minutes: int, urgent: bool, typical_response_minutes: int|null, overdue: bool}
     */
    public function noticeForBooking(Booking $booking): array
    {
        $faceUser = $booking->face;

        $messages = BookingMessage::query()
            ->where('booking_id', $booking->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['sender_id', 'created_at']);

        return $this->notice(
            $faceUser?->userable?->display_name,
            $faceUser,
            $booking->shootingDayStartsAt(),
            $messages,
        );
    }

    /**
     * Payload `data` de GET /producer/conversations/{conversation}/face-response.
     *
     * @return array{face_first_name: string, unread_email_after_minutes: int, urgent: bool, typical_response_minutes: int|null, overdue: bool}
     */
    public function noticeForConversation(Conversation $conversation): array
    {
        $candidature = $conversation->candidature;
        $face = $candidature?->face;

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['sender_id', 'created_at']);

        return $this->notice(
            $face?->display_name,
            $face?->user,
            $candidature?->mission?->shootingDayStartsAt(),
            $messages,
        );
    }

    /**
     * @param  iterable<object{sender_id: int, created_at: CarbonInterface|null}>  $messages
     * @return array{face_first_name: string, unread_email_after_minutes: int, urgent: bool, typical_response_minutes: int|null, overdue: bool}
     */
    private function notice(?string $displayName, ?User $faceUser, ?CarbonInterface $shootStart, iterable $messages): array
    {
        $urgent = $this->isUrgent($shootStart);
        // Sans compte Face rattaché, rien n'est affirmé : pas de délai habituel, pas de « sans réponse »
        $pendingSince = $faceUser === null ? null : $this->pendingSince($messages, $faceUser->id);

        $firstName = trim(explode(' ', trim((string) $displayName))[0]);

        return [
            'face_first_name' => $firstName !== '' ? $firstName : 'La Face',
            'unread_email_after_minutes' => $this->unreadEmailAfterMinutes($shootStart),
            'urgent' => $urgent,
            'typical_response_minutes' => $faceUser === null ? null : $this->typicalResponseMinutes($faceUser),
            'overdue' => $this->isOverdue($pendingSince, $urgent),
        ];
    }

    /**
     * Début d'attente par booking (clé = booking.id), une requête agrégée : MIN des messages non-Face
     * postérieurs au dernier message de la Face (la Face d'un booking est `bookings.face_id`, un User).
     *
     * @param  list<int>  $bookingIds
     * @return array<int, CarbonInterface>
     */
    public function pendingSinceForBookings(array $bookingIds): array
    {
        if ($bookingIds === []) {
            return [];
        }

        $rows = DB::table('booking_messages as m')
            ->join('bookings as b', 'b.id', '=', 'm.booking_id')
            ->whereIn('m.booking_id', $bookingIds)
            ->whereColumn('m.sender_id', '!=', 'b.face_id')
            ->whereRaw("m.created_at > COALESCE((SELECT MAX(f.created_at) FROM booking_messages f WHERE f.booking_id = m.booking_id AND f.sender_id = b.face_id), '1970-01-01 00:00:00')")
            ->groupBy('m.booking_id')
            ->selectRaw('m.booking_id as thread_id, MIN(m.created_at) as since')
            ->get();

        return $this->indexSince($rows);
    }

    /**
     * Début d'attente par candidature (clé = candidatures.id), une requête agrégée. La Face d'une
     * mission est le User dont `userable` est le profil `candidatures.face_id`.
     *
     * @param  list<int>  $candidatureIds
     * @return array<int, CarbonInterface>
     */
    public function pendingSinceForCandidatures(array $candidatureIds): array
    {
        if ($candidatureIds === []) {
            return [];
        }

        $rows = DB::table('messages as m')
            ->join('conversations as cv', 'cv.id', '=', 'm.conversation_id')
            ->join('candidatures as c', 'c.id', '=', 'cv.candidature_id')
            ->join('users as fu', function ($join): void {
                $join->on('fu.userable_id', '=', 'c.face_id')
                    ->where('fu.userable_type', '=', Face::class);
            })
            ->whereIn('cv.candidature_id', $candidatureIds)
            ->whereColumn('m.sender_id', '!=', 'fu.id')
            ->whereRaw("m.created_at > COALESCE((SELECT MAX(f.created_at) FROM messages f WHERE f.conversation_id = m.conversation_id AND f.sender_id = fu.id), '1970-01-01 00:00:00')")
            ->groupBy('cv.candidature_id')
            ->selectRaw('cv.candidature_id as thread_id, MIN(m.created_at) as since')
            ->get();

        return $this->indexSince($rows);
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return array<int, CarbonInterface>
     */
    private function indexSince(Collection $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->thread_id] = Carbon::parse((string) $row->since);
        }

        return $result;
    }
}
