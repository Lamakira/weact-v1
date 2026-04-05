<?php

declare(strict_types=1);

namespace App\Constants;

use Illuminate\Support\Str;

final class BeninCities
{
    /**
     * Official communes of Benin, sorted alphabetically for API consumers.
     *
     * @var list<string>
     */
    private const CITIES = [
        'Abomey',
        'Abomey-Calavi',
        'Adja-Ouèrè',
        'Adjarra',
        'Adjohoun',
        'Agbangnizoun',
        'Aguégués',
        'Akpro-Missérété',
        'Allada',
        'Aplahoué',
        'Athiémé',
        'Avrankou',
        'Banikoara',
        'Bantè',
        'Bassila',
        'Bembèrèkè',
        'Bohicon',
        'Bonou',
        'Bopa',
        'Boukoumbé',
        'Cobly',
        'Comè',
        'Copargo',
        'Cotonou',
        'Covè',
        'Dangbo',
        'Dassa-Zoumè',
        'Djakotomey',
        'Djidja',
        'Djougou',
        'Dogbo',
        'Glazoué',
        'Gogounou',
        'Grand-Popo',
        'Houéyogbé',
        'Ifangni',
        'Kalalé',
        'Kandi',
        'Karimama',
        'Kérou',
        'Kétou',
        'Klouékanmè',
        'Kouandé',
        'Kpomassè',
        'Lalo',
        'Lokossa',
        'Malanville',
        'Matéri',
        'Natitingou',
        'N\'Dali',
        'Nikki',
        'Ouidah',
        'Ouinhi',
        'Ouaké',
        'Ouèssè',
        'Parakou',
        'Pehunco',
        'Pèrèrè',
        'Pobè',
        'Porto-Novo',
        'Sakété',
        'Savalou',
        'Savè',
        'Ségbana',
        'Sèmè-Kpodji',
        'Sinendé',
        'Sô-Ava',
        'Tanguiéta',
        'Tchaourou',
        'Toffo',
        'Tori-Bossito',
        'Toucountouna',
        'Toviklin',
        'Za-Kpota',
        'Zagnanado',
        'Zè',
        'Zogbodomey',
    ];

    /**
     * @var array<string, string>
     */
    private const ALIASES = [
        'ab calavi' => 'Abomey-Calavi',
        'abomey calavi' => 'Abomey-Calavi',
        'abomey calavie' => 'Abomey-Calavi',
        'porto novo' => 'Porto-Novo',
        'porto-novo' => 'Porto-Novo',
    ];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return self::CITIES;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $city): array => [
                'value' => $city,
                'label' => $city,
            ],
            self::values(),
        );
    }

    public static function match(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalize($value);

        if ($normalized === '') {
            return null;
        }

        $lookup = self::normalizedLookup();

        return $lookup[$normalized] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function normalizedLookup(): array
    {
        $lookup = [];

        foreach (self::values() as $city) {
            $lookup[self::normalize($city)] = $city;
        }

        foreach (self::ALIASES as $alias => $city) {
            $lookup[self::normalize($alias)] = $city;
        }

        return $lookup;
    }

    private static function normalize(string $value): string
    {
        $asciiValue = Str::ascii(Str::of($value)->squish()->value());
        $normalized = preg_replace('/[^A-Za-z0-9]+/', ' ', $asciiValue);

        return strtolower(trim((string) $normalized));
    }
}
