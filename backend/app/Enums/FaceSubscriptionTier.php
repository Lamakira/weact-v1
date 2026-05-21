<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Entitlement tier of a Face. `Free` is the implicit floor for any Face
 * without an active paid subscription; it is a tier but never a `plan`.
 */
enum FaceSubscriptionTier: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';
    case Elite = 'elite';

    /**
     * French display name for this tier.
     */
    public function label(): string
    {
        return match ($this) {
            self::Free => 'Découverte',
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Elite => 'Élite',
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
}
