<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum token that does not rewrite `last_used_at` on every request.
 *
 * Sanctum stamps the column on each authenticated call (one UPDATE per request).
 * The value only feeds the admin "active users" counter, so a one-minute
 * granularity is plenty: a stamp younger than a minute is not persisted.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Minimum age (seconds) of the persisted stamp before it is refreshed.
     */
    public const LAST_USED_REFRESH_SECONDS = 60;

    /**
     * The HTTP request during which this instance was looked up, if any.
     *
     * MUST stay private: a User captured by an after-response closure carries
     * its current token, and the closure is serialised. Eloquent only serialises
     * attributes/relations, but a protected/public WeakReference property would
     * be walked by the serializer and fail (WeakReference cannot be serialised).
     *
     * @var \WeakReference<Request>|null
     */
    private ?\WeakReference $resolvedFor = null;

    /**
     * Same lookup as Sanctum's, but remembers the request it ran for so a later
     * consumer (EnsureApiBearerToken) can tell a token loaded during THIS
     * request from one memoised by the guard across requests (tests, Octane).
     *
     * @param  string  $token
     */
    public static function findToken($token)
    {
        $found = parent::findToken($token);

        if ($found instanceof self) {
            $found->resolvedFor = \WeakReference::create(request());
        }

        return $found;
    }

    /**
     * True when this exact instance was looked up while handling $request.
     */
    public function wasResolvedFor(Request $request): bool
    {
        return $this->resolvedFor?->get() === $request;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists && $this->isOnlyFreshLastUsedTouch()) {
            // Drop the in-memory bump: nothing to persist, model stays clean.
            $this->setAttribute('last_used_at', $this->getOriginal('last_used_at'));

            return true;
        }

        return parent::save($options);
    }

    /**
     * True when the only pending change is a `last_used_at` stamp over a
     * persisted stamp that is less than a minute old.
     */
    private function isOnlyFreshLastUsedTouch(): bool
    {
        $dirty = $this->getDirty();

        if (array_keys($dirty) !== ['last_used_at']) {
            return false;
        }

        $previous = $this->getOriginal('last_used_at');

        if ($previous === null) {
            return false;
        }

        return $this->asDateTime($previous)->gt(now()->subSeconds(self::LAST_USED_REFRESH_SECONDS));
    }
}
