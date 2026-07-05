<?php

declare(strict_types=1);

namespace App\Support;

final class Sql
{
    /**
     * Escape a user-supplied term for use inside a LIKE pattern.
     *
     * Escapes the backslash FIRST, then the LIKE wildcards — otherwise a
     * trailing '\' in the term escapes the closing '%' of the pattern, and
     * an input like '\%' leaves a live unescaped wildcard behind.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
