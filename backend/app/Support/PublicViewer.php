<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Producer;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Classe de visiteur d'un endpoint public (lot B13) : `producer` pour un Producer connecté
 * (jeton Bearer valide, compte actif, sans condition d'abonnement), `guest` pour tout le
 * reste (anonyme, Face, admin, jeton invalide ou expiré). Jamais d'erreur : un jeton
 * douteux rend simplement la vue invité.
 *
 * Résolue une seule fois par requête, uniquement sur les routes portant le middleware
 * `public.viewer` ; ailleurs la classe vaut `guest` sans aucune lecture de jeton. Le cache
 * public (CachePublicResponse) l'intègre à sa clé : une réponse Producer n'est jamais
 * servie à un invité.
 */
final class PublicViewer
{
    public const GUEST = 'guest';

    public const PRODUCER = 'producer';

    private const ATTRIBUTE = 'public_viewer_class';

    /**
     * Résout et mémorise la classe du visiteur (appelée par le middleware `public.viewer`).
     */
    public static function resolve(Request $request): string
    {
        $class = self::producerFromBearer($request) !== null ? self::PRODUCER : self::GUEST;
        $request->attributes->set(self::ATTRIBUTE, $class);

        return $class;
    }

    /**
     * Classe déjà résolue pour cette requête ; `guest` si la route ne la résout pas.
     */
    public static function classFor(Request $request): string
    {
        $class = $request->attributes->get(self::ATTRIBUTE);

        return $class === self::PRODUCER ? self::PRODUCER : self::GUEST;
    }

    public static function isProducer(Request $request): bool
    {
        return self::classFor($request) === self::PRODUCER;
    }

    /**
     * Mêmes contrôles d'expiration que EnsureApiBearerToken ; seuls les jetons d'un User
     * Producer actif comptent (un jeton admin ou Face reste invité).
     */
    private static function producerFromBearer(Request $request): ?User
    {
        $plainTextToken = $request->bearerToken();

        if ($plainTextToken === null || $plainTextToken === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if ($accessToken === null || self::isExpired($accessToken)) {
            return null;
        }

        $user = $accessToken->tokenable;

        if (! $user instanceof User
            || $user->userable_type !== Producer::class
            || ! $user->is_active) {
            return null;
        }

        return $user;
    }

    private static function isExpired(PersonalAccessToken $accessToken): bool
    {
        $expiration = config('sanctum.expiration');

        if ($expiration && ! $accessToken->created_at->gt(now()->subMinutes((int) $expiration))) {
            return true;
        }

        return $accessToken->expires_at !== null && $accessToken->expires_at->isPast();
    }
}
