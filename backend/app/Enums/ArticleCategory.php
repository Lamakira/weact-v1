<?php

declare(strict_types=1);

namespace App\Enums;

enum ArticleCategory: string
{
    case ConseilsFace = 'conseils-face';
    case GuideProducteur = 'guide-producteur';
    case Actualites = 'actualites';

    /**
     * Get the display name in French for this article category.
     */
    public function label(): string
    {
        return match ($this) {
            self::ConseilsFace => 'Conseils Face',
            self::GuideProducteur => 'Guide Producteur',
            self::Actualites => 'Actualités',
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
