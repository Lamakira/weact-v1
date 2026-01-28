<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Message model.
 *
 * Includes sender information and computed is_own_message field.
 */
class MessageResource extends JsonResource
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
            'id' => $this->id,
            'content' => $this->content,
            'sender_id' => $this->sender_id,
            'sender_type' => $this->sender_type,
            'sender_name' => $this->getSenderName(),
            'is_own_message' => $currentUser && $this->sender_id === $currentUser->id,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Get the display name of the message sender.
     */
    private function getSenderName(): string
    {
        // The sender is a User, and User has userable (Face or Producer)
        $sender = $this->sender;

        if (!$sender) {
            return 'Utilisateur';
        }

        // Get the userable (Face or Producer) and retrieve its display name
        // If userable is not loaded, load it
        if (!$sender->relationLoaded('userable')) {
            $sender->load('userable');
        }

        $userable = $sender->userable;

        if ($userable) {
            return $userable->display_name ?? 'Utilisateur';
        }

        return 'Utilisateur';
    }
}
