<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ImageMetadataStripper;
use App\Support\UploadedMedia;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Plafonds de dimensions d'une image (côté et total de pixels : protège le worker
 * de queue qui décode pour générer les variantes). Ne lit que les en-têtes : aucun
 * décodage dans la requête. Rejet précoce ; la vérification d'autorité est refaite
 * par UploadedMedia::storeImage sur le fichier nettoyé.
 */
class MaxImageDimensions implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || $value->getRealPath() === false) {
            return; // les autres règles (file/image) se prononcent
        }

        $path = (string) $value->getRealPath();
        if (ImageMetadataStripper::format($path) === null) {
            return; // ni JPEG ni PNG : les autres règles (image/types) se prononcent
        }

        // Deux mesures : l'en-tête tel que le décodeur le lira, et getimagesize(). Elles peuvent
        // diverger sur un fichier fabriqué : on applique les plafonds aux deux et on refuse
        // (fail closed) si aucune ne sait lire un JPEG/PNG.
        $parsed = ImageMetadataStripper::dimensions($path);
        $native = @getimagesize($path);
        if ($parsed === null || $native === false) {
            $fail('Image illisible ou corrompue.');

            return;
        }

        foreach ([$parsed, [$native[0], $native[1]]] as [$width, $height]) {
            $error = UploadedMedia::dimensionError($width, $height);
            if ($error !== null) {
                $fail($error);

                return;
            }
        }
    }
}
