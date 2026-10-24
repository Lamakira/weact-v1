<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Support\Whatsapp;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Normalisation E.164 des numéros de profil (texte libre) et formatage d'affichage.
 */
class WhatsappNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function e164Provider(): array
    {
        return [
            'dix chiffres 01 sans préfixe (majorité)' => ['0197451233', '2290197451233'],
            'dix chiffres 01 avec espaces' => ['01 97 45 12 33', '2290197451233'],
            '+229 et dix chiffres' => ['+229 01 97 45 12 33', '2290197451233'],
            '+229 collé et dix chiffres' => ['+2290197451233', '2290197451233'],
            '+229 et huit chiffres (ancien format)' => ['+229 97 45 12 33', '2290197451233'],
            'huit chiffres nus (ancien format)' => ['97451233', '2290197451233'],
            '229 et huit chiffres sans plus' => ['22997451233', '2290197451233'],
            '229 et dix chiffres sans plus' => ['2290197451233', '2290197451233'],
            '00229 et dix chiffres' => ['00229 01 97 45 12 33', '2290197451233'],
            '00229 et huit chiffres' => ['0022997451233', '2290197451233'],
            '+228 et huit chiffres (Togo)' => ['+228 90 12 34 56', '22890123456'],
            '00228 (Togo)' => ['0022890123456', '22890123456'],
            'France' => ['+33 6 12 34 56 78', '33612345678'],
            'trop court' => ['0197', null],
            'sept chiffres' => ['9745123', null],
            'neuf chiffres sans préfixe' => ['019745123', null],
            'onze chiffres hors 229 sans préfixe' => ['12345678901', null],
            'plus mais trop court' => ['+12345', null],
            'plus mais trop long' => ['+1234567890123456', null],
            'chaîne vide' => ['', null],
            'espaces' => ['   ', null],
            'lettres' => ['pas de numéro', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('e164Provider')]
    public function test_to_e164_normalizes_real_world_formats(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, Whatsapp::toE164($raw));
    }

    public function test_display_format_groups_benin_numbers_and_prefixes_others(): void
    {
        $this->assertSame('+229 01 97 45 12 33', Whatsapp::formatForDisplay('0197451233'));
        $this->assertSame('+229 01 97 45 12 33', Whatsapp::formatForDisplay('+229 97 45 12 33'));
        $this->assertSame('+22890123456', Whatsapp::formatForDisplay('+228 90 12 34 56'));
        $this->assertNull(Whatsapp::formatForDisplay(''));
        $this->assertNull(Whatsapp::formatForDisplay(null));
    }
}
