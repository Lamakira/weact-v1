<?php

declare(strict_types=1);

namespace App\Enums;

enum FaceSubscriptionPlan: string
{
    case AnnualPremium = 'annual_premium';

    /**
     * Get the display name in French for this subscription plan.
     */
    public function label(): string
    {
        return match ($this) {
            self::AnnualPremium => 'Premium annuel',
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
