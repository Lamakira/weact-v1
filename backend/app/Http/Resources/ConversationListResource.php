<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Lightweight API Resource for Conversation list view.
 *
 * Does not include full messages array - only latest message preview.
 * Used for displaying conversation list with unread counts.
 */
class ConversationListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $latestMessage = $this->latestMessage;

        return [
            'id' => $this->id,
            'candidature_id' => $this->candidature_id,
            'mission_title' => $this->candidature?->mission?->titre ?? '',
            'other_participant' => $this->getOtherParticipant($currentUser),
            'latest_message' => $latestMessage ? [
                'content' => Str::limit($latestMessage->content, 50),
                'sender_name' => $latestMessage->sender?->userable?->display_name ?? 'Inconnu',
                'is_mine' => $latestMessage->sender_id === $currentUser?->id,
                'created_at' => $latestMessage->created_at->toIso8601String(),
            ] : null,
            'unread_count' => $currentUser ? $this->unreadCountFor($currentUser) : 0,
            'updated_at' => $this->updated_at->toIso8601String(),
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
                'id' => $producer->id,
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
            'id' => $face->id,
            'name' => $face->display_name,
            'photo_url' => $face->profile_photo_url,
            'type' => 'face',
        ];
    }
}
