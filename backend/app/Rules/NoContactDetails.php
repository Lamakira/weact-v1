<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Normalizer;

/**
 * Refuse les coordonnées dans un texte libre échangé avant paiement (lot B12) : numéro de
 * téléphone, e-mail, @pseudo, lien web ou de réseau social. Le chat n'ouvre qu'après le
 * paiement ; ce filtre empêche de le contourner par le brief d'un booking ou la motivation
 * d'une candidature.
 *
 * Le texte est normalisé (NFKC) avant détection : chiffres et arobase « pleine chasse »
 * (９, ＠) deviennent leurs équivalents ASCII. Faux positifs évités : dates (12.10.2026),
 * montants suivis d'une devise (15 000 000 FCFA), « @ » isolé.
 */
final class NoContactDetails implements ValidationRule
{
    public const MESSAGE = 'Pour votre sécurité, ne partagez pas de coordonnées (numéro de téléphone, e-mail, @pseudo ou lien vers un site ou un réseau social). Les échanges se font sur WeAct, la messagerie s\'ouvre après le paiement.';

    /** Au moins 8 chiffres, séparés au plus par 3 espaces, points, tirets ou parenthèses. */
    private const PHONE = '/(?<!\d)(?:\+|00)?\d(?:[\s.\-()]{0,3}\d){7,}(?!\d)(?!\s*(?:f\s?cfa|xof|cfa|francs?)\b)/iu';

    private const EMAIL = '/[\p{L}\p{N}._%+\-]+\s*(?:@|\(at\)|\[at\])\s*[\p{L}\p{N}\-]+(?:\.[\p{L}\p{N}\-]+)*\.\p{L}{2,}/iu';

    private const HANDLE = '/(?<![\p{L}\p{N}_.])@[\p{L}\p{N}_][\p{L}\p{N}_.]+/u';

    private const URL = '/(?:https?:\/\/|www\.)\S+/iu';

    private const SOCIAL_DOMAIN = '/(?<![\p{L}\p{N}\-])(?:[\p{L}\p{N}\-]+\.)*(?:instagram\.com|instagr\.am|facebook\.com|fb\.com|fb\.me|m\.me|tiktok\.com|snapchat\.com|twitter\.com|x\.com|linkedin\.com|youtube\.com|youtu\.be|wa\.me|whatsapp\.com|t\.me|telegram\.me|threads\.net)\b/iu';

    /** Date jour-mois-année (12.10.2026, 12-10-2026) : pas un numéro de téléphone. */
    private const DATE_LIKE = '/^\d{1,2}[.\-]\d{1,2}[.\-]\d{4}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (self::containsContactDetails($value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function containsContactDetails(string $value): bool
    {
        $text = class_exists(Normalizer::class)
            ? (Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value)
            : $value;

        foreach ([self::EMAIL, self::URL, self::SOCIAL_DOMAIN, self::HANDLE] as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        if (preg_match_all(self::PHONE, $text, $matches) > 0) {
            foreach ($matches[0] as $match) {
                if (preg_match(self::DATE_LIKE, trim($match)) !== 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
