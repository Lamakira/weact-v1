<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Fil de booking dans la liste « Messages » : même forme que ConversationListResource
 * (le frontend les mélange), avec kind = 'booking' et l'UUID du booking comme `id`.
 *
 * Attend `face.userable`, `producer.userable`, `latestMessage.sender.userable` chargés
 * et `unread_count` pré-calculé (Booking::withUnreadCountFor) : aucune requête par ligne.
 *
 * @mixin \App\Models\Booking
 */
class BookingThreadListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Booking $booking */
        $booking = $this->resource;
        $currentUser = $request->user();
        $latestMessage = $booking->latestMessage;
        $title = self::title($booking);

        return [
            'kind' => 'booking',
            'id' => $booking->uuid,
            'booking_id' => $booking->uuid,
            'candidature_id' => null,
            'mission_title' => $title,
            'other_participant' => $this->otherParticipant($booking, $currentUser?->id),
            // Pas de contexte de candidature (étapes, montants) pour un fil de booking
            'context' => null,
            'latest_message' => $latestMessage ? [
                'content' => Str::limit($latestMessage->content, 50),
                'sender_name' => data_get($latestMessage, 'sender.userable.display_name', 'Inconnu'),
                'is_mine' => $latestMessage->sender_id === $currentUser?->id,
                'created_at' => $latestMessage->created_at->toIso8601String(),
            ] : null,
            'unread_count' => (int) ($booking->getAttribute('unread_count') ?? 0),
            'updated_at' => ($latestMessage->created_at ?? $booking->updated_at)->toIso8601String(),
        ];
    }

    /**
     * « Booking · {type de contenu} · {date} » (date de début, fuseau métier).
     */
    public static function title(Booking $booking): string
    {
        return collect([
            'Booking',
            $booking->type_contenu,
            $booking->date_debut ? Booking::formatForBusiness($booking->date_debut, 'd/m/Y') : null,
        ])->filter()->implode(' · ');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function otherParticipant(Booking $booking, ?int $currentUserId): ?array
    {
        $other = $currentUserId === $booking->face_id ? $booking->producer : $booking->face;
        $profile = $other?->userable;

        if ($profile === null) {
            return null;
        }

        return [
            'id' => $profile->uuid,
            'name' => $profile->display_name,
            'photo_url' => $profile->profile_photo_url,
            'profile_photo_thumbnail_url' => $profile->thumbnail_url,
            'type' => $profile instanceof Face ? 'face' : 'producer',
        ];
    }
}
