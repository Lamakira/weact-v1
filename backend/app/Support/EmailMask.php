<?php

declare(strict_types=1);

namespace App\Support;

final class EmailMask
{
    /**
     * « aicha@gmail.com » devient « a•••••@gmail.com » : première lettre, puis des puces.
     */
    public static function mask(string $email): string
    {
        $email = trim($email);
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '•••••';
        }

        return mb_substr(substr($email, 0, $at), 0, 1).str_repeat('•', 5).substr($email, $at);
    }
}
