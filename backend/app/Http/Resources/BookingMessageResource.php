<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sender = $this->sender;
        $currentUser = $request->user();

        return [
            'id'             => $this->id,
            'booking_id'     => $this->booking_id,
            'sender_id'      => $this->sender_id,
            'sender_name'    => $this->resolveSenderName($sender),
            'content'        => $this->content,
            'is_own_message' => $currentUser && $this->sender_id === $currentUser->id,
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * @param  \App\Models\User|null  $sender
     */
    private function resolveSenderName(mixed $sender): string
    {
        if (! $sender) {
            return 'Utilisateur';
        }

        if (! $sender->relationLoaded('userable')) {
            $sender->load('userable');
        }

        return $sender->userable?->display_name ?? $sender->name ?? 'Utilisateur';
    }
}
