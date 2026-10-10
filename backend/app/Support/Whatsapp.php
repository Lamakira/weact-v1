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
}
