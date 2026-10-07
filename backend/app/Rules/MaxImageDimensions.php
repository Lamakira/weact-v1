<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\UploadedMedia;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Plafond de dimensions d'une image (protège le worker de queue qui décode pour
 * générer les variantes). Ne lit que l'en-tête (getimagesize) : aucun décodage
 * dans la requête.
 */
class MaxImageDimensions implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || $value->getRealPath() === false) {
            return; // les autres règles (file/image) se prononcent
        }

        $size = @getimagesize($value->getRealPath());
        if ($size === false) {
            return;
        }

        $max = UploadedMedia::MAX_IMAGE_DIMENSION;
        if ($size[0] > $max || $size[1] > $max) {
            $fail("Image trop grande : {$max} pixels maximum par côté.");
        }
    }
}
