<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Conversation model.
 *
 * Includes messages, other participant info, and mission context.
 */
class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        return [
            'id' => $this->uuid,
            'candidature_id' => $this->candidature_id,
            'mission_title' => $this->candidature?->mission?->titre ?? '',
            'other_participant' => $this->getOtherParticipant($currentUser),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'unread_count' => $currentUser ? $this->unreadCountFor($currentUser) : 0,
        ];
    }

    /**
     * Get the other participant in the conversation.
     *
     * If current user is a Face, return the Producer.
     * If current user is a Producer, return the Face.
     *
     * @return array<string, mixed>|null
     */
    private function getOtherParticipant($currentUser): ?array
    {
        if (!$currentUser) {
            return null;
        }

        $candidature = $this->candidature;

        if (!$candidature) {
            return null;
        }

        // If current user is a Face, return the Producer
        if ($currentUser->userable_type === Face::class) {
            $producer = $candidature->mission?->producer;

            if (!$producer) {
                return null;
            }

            return [
                'id' => $producer->uuid,
                'name' => $producer->display_name,
                'photo_url' => $producer->profile_photo_url,
                'type' => 'producer',
            ];
        }

        // If current user is a Producer, return the Face
        $face = $candidature->face;

        if (!$face) {
            return null;
        }

        return [
            'id' => $face->uuid,
            'name' => $face->display_name,
            'photo_url' => $face->profile_photo_url,
            'type' => 'face',
        ];
    }
}
