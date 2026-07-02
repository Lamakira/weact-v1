<?php

declare(strict_types=1);

namespace App\Enums;

enum FaceSubscriptionPlan: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Elite = 'elite';

    /**
     * French display name for this paid plan.
     */
    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Elite => 'Élite',
        };
    }

    /**
     * The entitlement tier this paid plan grants.
     */
    public function tier(): FaceSubscriptionTier
    {
        return match ($this) {
            self::Starter => FaceSubscriptionTier::Starter,
            self::Pro => FaceSubscriptionTier::Pro,
            self::Elite => FaceSubscriptionTier::Elite,
        };
    }

    /**
     * Annual price for this paid plan in integer XOF, sourced from
     * config/face_subscription_tiers.php (the single pricing source of truth).
     */
    public function price(): int
    {
        return (int) config('face_subscription_tiers.tiers.'.$this->value.'.price');
    }

    /**
     * French phrase summarising the premium media (album photos + videos
     * beyond the Free baseline of 1 photo / 0 videos) this paid plan grants.
     *
     * Returned as a lowercase noun phrase ready to be embedded in lifecycle
     * copy: "{...sont visibles}" / "{...redeviennent privées}" / "Renouvelez
     * pour garder {...} publiques". The phrasing is intentionally plural so
     * the surrounding verb agreement ('sont', 'redeviennent') stays uniform
     * across the 3 tiers (each phrase includes at least 2 distinct media types).
     */
    public function premiumMediaSummary(): string
    {
        return match ($this) {
            self::Starter => 'votre 2ème photo d\'album et votre vidéo de présentation',
            self::Pro => 'vos photos 2 à 4 d\'album, votre vidéo de présentation et votre vidéo de jeu',
            self::Elite => 'vos photos 2 à 6 d\'album, votre vidéo de présentation, vos 2 vidéos de jeu et votre vidéo UGC',
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
