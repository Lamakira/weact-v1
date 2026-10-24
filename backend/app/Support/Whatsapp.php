<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helpers around the free-text WhatsApp numbers collected on Face and Producer profiles.
 */
final class Whatsapp
{
    /**
     * A number is dialable when it holds at least one digit once formatting
     * (spaces, "+", dashes, parentheses) is stripped.
     */
    public static function isDialable(?string $whatsapp): bool
    {
        if ($whatsapp === null) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $whatsapp) ?? '';

        return $digits !== '';
    }

    /**
     * Normalise un numéro de profil (texte libre) au format attendu par l'API WhatsApp :
     * chiffres seuls, indicatif pays compris, sans « + ». Null si le numéro n'est pas envoyable.
     *
     * Formats rencontrés : « 01… » à 10 chiffres sans indicatif (Bénin, majorité), « +229 » suivi de
     * 10 chiffres, « +229 » suivi de 8 chiffres (ancien format, devenu « 01 » + 8 chiffres), 8 chiffres
     * nus (ancien format béninois), numéros étrangers préfixés par « + » ou « 00 ».
     */
    public static function toE164(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        if (str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }

        $length = strlen($digits);

        // Formats béninois sans indicatif : réservés aux numéros saisis sans « + » ni « 00 ».
        if (! $international) {
            if ($length === 10 && str_starts_with($digits, '01')) {
                return '229'.$digits;
            }
            if ($length === 8) {
                return '22901'.$digits;
            }
        }

        if (str_starts_with($digits, '229')) {
            if ($length === 11) {
                return '22901'.substr($digits, 3);
            }
            if ($length === 13) {
                return $digits;
            }
        }

        if ($international && $length >= 10 && $length <= 15) {
            return $digits;
        }

        return null;
    }

    /**
     * Numéro normalisé affiché à l'utilisateur : « +229 01 97 45 12 33 » pour le Bénin,
     * « +{chiffres} » sinon. Null si le numéro n'est pas envoyable.
     */
    public static function formatForDisplay(?string $raw): ?string
    {
        $e164 = self::toE164($raw);
        if ($e164 === null) {
            return null;
        }

        if (strlen($e164) === 13 && str_starts_with($e164, '229')) {
            return '+229 '.trim(chunk_split(substr($e164, 3), 2, ' '));
        }

        return '+'.$e164;
    }
}
