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
     * Build a unique, URL-safe username from a Face's first and last name.
     *
     * The uniqueness check here is advisory: the unique index on `faces.username`
     * remains the source of truth, and FaceRegistrationService retries on violation.
     */
    public function generate(string $prenom, string $nom): string
    {
        $base = $this->slugify($prenom.' '.$nom);

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
        $base = Str::substr($this->slugify($prenom.' '.$nom), 0, self::BASE_LENGTH);

        return $base.Str::lower(Str::random(4));
    }

    private function slugify(string $fullName): string
    {
        $base = Str::of($fullName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->substr(0, self::BASE_LENGTH)
            ->value();

        return $base !== '' ? $base : self::FALLBACK_BASE;
    }

    private function isTaken(string $candidate): bool
    {
        if (in_array($candidate, self::RESERVED, true)) {
            return true;
        }

        return Face::query()->where('username', $candidate)->exists();
    }
}
