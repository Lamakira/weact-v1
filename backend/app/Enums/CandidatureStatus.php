<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status workflow for candidatures.
 *
 * Status transitions:
 * - pending → accepted (Producer accepts) or rejected (Producer rejects) or cancelled (Face withdraws)
 * - accepted → confirmed (Face confirms participation) - unlocks chat
 * - confirmed → in_progress (Mission starts)
 * - in_progress → completed (Mission finishes) - enables ratings
 */
enum CandidatureStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Get the display name in French for this candidature status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Confirmed => 'Confirmée',
            self::InProgress => 'En cours',
            self::Completed => 'Terminée',
            self::Rejected => 'Refusée',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * Get all enum values as an array of strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if this status allows chat access between Face and Producer.
     *
     * Chat is unlocked after Producer accepts the candidature (Story 7.2).
     */
    public function allowsChatAccess(): bool
    {
        return in_array($this, [
            self::Accepted,
            self::Confirmed,
            self::InProgress,
            self::Completed,
        ], true);
    }

    /**
     * Check if this status allows rating after mission completion.
     *
     * Ratings are only possible after candidature is completed (Story 8.x).
     */
    public function allowsRatings(): bool
    {
        return $this === self::Completed;
    }
}
