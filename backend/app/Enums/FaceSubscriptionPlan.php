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
     * Get all enum values as an array of strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
