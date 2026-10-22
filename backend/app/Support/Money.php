<?php

namespace App\Support;

/**
 * Formatage unique des montants et nombres affichés (API, e-mails, notifications).
 *
 * « 12 500 F CFA » : milliers séparés par une espace fine insécable (U+202F),
 * espaces insécables (U+00A0) autour de « F CFA » pour qu'un montant ne se
 * coupe jamais. Affichage uniquement : le code devise FedaPay (XOF) est inchangé.
 */
final class Money
{
    private const NNBSP = "\u{202F}";

    private const NBSP = "\u{00A0}";

    /** Entier avec séparateur de milliers français (« 3 941 »). */
    public static function number(int|float|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 0, ',', self::NNBSP);
    }

    /** Montant en francs CFA : « 12 500 F CFA ». */
    public static function format(int|float|string|null $amount): string
    {
        return self::number($amount).self::NBSP.'F'.self::NBSP.'CFA';
    }

    /** Tarif par période : « 25 000 F CFA / jour ». */
    public static function perPeriod(int|float|string|null $amount, string $period): string
    {
        return self::format($amount).self::NBSP.'/'.self::NBSP.$period;
    }
}
