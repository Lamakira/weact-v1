<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Closed lists of countries and (feminine) nationalities offered to Faces.
 *
 * The lists live in static JSON files (resources/data), mirrored byte for byte
 * in frontend/src/shared/constants: Intl.DisplayNames depends on the ICU
 * version, so the server never recomputes them.
 */
final class Territories
{
    /** @var list<string>|null */
    private static ?array $countries = null;

    /** @var list<string>|null */
    private static ?array $nationalities = null;

    /**
     * @return list<string>
     */
    public static function countries(): array
    {
        return self::$countries ??= self::load('countries.json');
    }

    /**
     * @return list<string>
     */
    public static function nationalities(): array
    {
        return self::$nationalities ??= self::load('nationalities.json');
    }

    /**
     * @return list<string>
     */
    private static function load(string $file): array
    {
        $decoded = json_decode((string) file_get_contents(resource_path('data/'.$file)), true, 512, JSON_THROW_ON_ERROR);

        /** @var list<string> $decoded */
        return $decoded;
    }
}
