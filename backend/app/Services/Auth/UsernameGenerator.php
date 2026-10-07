<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Face;
use Illuminate\Support\Str;

class UsernameGenerator
{
    /**
     * Maximum length of the `faces.username` column.
     */
    public const MAX_LENGTH = 50;

    /**
     * Minimum length of a username, for a generated one and for a user-chosen one.
     */
    public const MIN_LENGTH = 3;

    /**
     * Room left for the disambiguation suffix (counter or random token).
     */
    private const BASE_LENGTH = 46;

    /**
     * Fallback base when the name yields no usable characters (non-latin script, emoji only...).
     *
     * Deliberately NOT 'face': that word is reserved, so the ladder would hand the
     * very first such user a `face2` handle.
     */
    private const FALLBACK_BASE = 'membre';

    /**
     * Usernames that would collide with an existing public route segment.
     *
     * `/faces/options` is a real sibling route of `/faces/{username}`.
     *
     * @var list<string>
     */
    public const RESERVED = [
        'admin',
        'api',
        'face',
        'faces',
        'login',
        'logout',
        'options',
        'producer',
        'producers',
        'register',
    ];

    /**
     * Build a unique, URL-safe username from a Face's first name and the INITIAL
     * of the last name (« Aïcha Kouassi » → `aichak`).
     *
     * The username is public (listing payloads, /faces/{username} URL): it must
     * never spell out the last name. Only generated handles follow this rule —
     * usernames already stored (all chosen at signup with the old
     * « prenom + nom » form, before this generator shipped) and usernames picked
     * by the Face are deliberately NOT renamed, so no data migration is needed.
     *
     * The uniqueness check here is advisory: the unique index on `faces.username`
     * remains the source of truth, and FaceRegistrationService retries on violation.
     */
    public function generate(string $prenom, string $nom): string
    {
        $base = $this->slugify($prenom, $nom);

        $candidate = $base;
        $counter = 1;

        while ($this->isTaken($candidate) && $counter < self::MAX_LENGTH) {
            $counter++;
            $candidate = Str::substr($base, 0, self::BASE_LENGTH).$counter;
        }

        return $candidate;
    }

    /**
     * Build a username guaranteed not to collide with the deterministic ladder,
     * used when a concurrent signup won the race on the unique index.
     */
    public function generateWithRandomSuffix(string $prenom, string $nom): string
    {
        $base = Str::substr($this->slugify($prenom, $nom), 0, self::BASE_LENGTH);

        return $base.Str::lower(Str::random(4));
    }

    private function slugify(string $prenom, string $nom): string
    {
        $initial = Str::substr($this->fold($nom), 0, 1);
        $base = Str::substr($this->fold($prenom), 0, self::BASE_LENGTH - strlen($initial)).$initial;

        // Below the minimum (« A B » → `ab`) the handle would be one the user could
        // not even re-submit through the profile form: use the fallback instead.
        return strlen($base) >= self::MIN_LENGTH ? $base : self::FALLBACK_BASE;
    }

    private function fold(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();
    }

    private function isTaken(string $candidate): bool
    {
        if (in_array($candidate, self::RESERVED, true)) {
            return true;
        }

        return Face::query()->where('username', $candidate)->exists();
    }
}
