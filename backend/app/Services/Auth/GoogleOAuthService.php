<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cryptographic plumbing of the Google flow: the signed single-use `state` that
 * survives the round-trip to Google, and the one-shot code that hands the Sanctum
 * token back to the SPA.
 */
class GoogleOAuthService
{
    /** Intent carried through the round-trip: which button the user pressed. */
    public const INTENT_FACE = 'face';

    public const INTENT_PRODUCER = 'producer';

    public const INTENT_LOGIN = 'login';

    /**
     * Re-authentication before an irreversible action, for an account that has no
     * password to confirm with.
     */
    public const INTENT_REAUTH = 'reauth';

    /** @var list<string> */
    public const INTENTS = [
        self::INTENT_FACE,
        self::INTENT_PRODUCER,
        self::INTENT_LOGIN,
        self::INTENT_REAUTH,
    ];

    private const STATE_TTL_SECONDS = 600;

    private const EXCHANGE_TTL_SECONDS = 120;

    private const PENDING_TTL_SECONDS = 900;

    private const REAUTH_TTL_SECONDS = 300;

    /**
     * Lifetime of a `:spent` marker: it only has to outlive the race window (the value
     * key is forgotten right after it is taken). On the database cache store an expired
     * row is only deleted when that same key is read, so a longer TTL just piles up rows.
     */
    private const SPENT_MARKER_TTL_SECONDS = 60;

    /**
     * Build a tamper-proof, single-use `state`.
     *
     * Socialite's stateful mode keeps state in the session, which would have to
     * survive SPA(:5173) → API(:8000) → accounts.google.com → API(:8000) with
     * SameSite=lax across three origins — and, decisively, session state cannot
     * carry the intent. Google enforces an exact-match redirect_uri, so the intent
     * cannot ride there either.
     */
    public function issueState(string $intent, ?string $redirect, string $browserNonce): string
    {
        $nonce = Str::random(32);

        $payload = json_encode([
            'intent' => $intent,
            'redirect' => $redirect,
            // Binds the whole flow to the browser that started it: only a SHA-256
            // of the SPA-held nonce travels, and the exchange re-checks it. Without
            // it, a victim could be handed an attacker-minted callback URL.
            'binding' => $this->bindingFor($browserNonce),
            'nonce' => $nonce,
            'exp' => now()->addSeconds(self::STATE_TTL_SECONDS)->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        Cache::put($this->stateKey($nonce), true, self::STATE_TTL_SECONDS);

        return $this->base64UrlEncode($payload).'.'.hash_hmac('sha256', $payload, $this->signingKey());
    }

    /**
     * Verify and consume a `state`.
     *
     * @return array{intent: string, redirect: string|null, binding: string|null}|null null on a tampered,
     *                                                                                 expired or replayed state
     */
    public function consumeState(?string $state): ?array
    {
        if (! is_string($state) || ! str_contains($state, '.')) {
            return null;
        }

        [$encoded, $signature] = explode('.', $state, 2);

        $payload = $this->base64UrlDecode($encoded);

        if ($payload === null) {
            return null;
        }

        if (! hash_equals(hash_hmac('sha256', $payload, $this->signingKey()), $signature)) {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $intent = $decoded['intent'] ?? null;
        $exp = $decoded['exp'] ?? null;
        $nonce = $decoded['nonce'] ?? null;

        if (! in_array($intent, self::INTENTS, true) || ! is_int($exp) || ! is_string($nonce)) {
            return null;
        }

        if ($exp < now()->getTimestamp()) {
            return null;
        }

        // Absent from the cache ⇒ already consumed (replay) or expired.
        if ($this->consumeOnce($this->stateKey($nonce)) === null) {
            return null;
        }

        $redirect = $decoded['redirect'] ?? null;
        $binding = $decoded['binding'] ?? null;

        return [
            'intent' => $intent,
            'redirect' => is_string($redirect) ? $redirect : null,
            'binding' => is_string($binding) ? $binding : null,
        ];
    }

    /**
     * Does the nonce posted to /exchange match the one the flow was started with?
     */
    public function bindingMatches(mixed $binding, string $browserNonce): bool
    {
        return is_string($binding) && $binding !== '' && hash_equals($binding, $this->bindingFor($browserNonce));
    }

    /**
     * Mint the one-shot code the SPA trades for its Sanctum token.
     *
     * The token never travels in the redirect URL: a bearer valid for 30 days
     * (config/sanctum.php) must not be written into browser history, `Referer`
     * headers or access logs. This code is single-use and lives 2 minutes; it is
     * not a credential, only a claim ticket.
     *
     * @param  array<string, mixed>  $payload
     */
    public function issueExchangeCode(array $payload): string
    {
        $code = Str::random(64);

        Cache::put($this->exchangeKey($code), $payload, self::EXCHANGE_TTL_SECONDS);

        return $code;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function consumeExchangeCode(string $code): ?array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $this->consumeOnce($this->exchangeKey($code));

        return $payload;
    }

    /**
     * Mint the token that carries a *not yet created* account through the
     * finalisation screen (role, date of birth, consent).
     *
     * @param  array<string, mixed>  $payload
     */
    public function issuePendingToken(array $payload): string
    {
        $token = Str::random(64);

        Cache::put($this->pendingKey($token), $payload, self::PENDING_TTL_SECONDS);

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function consumePendingToken(string $token): ?array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $this->consumeOnce($this->pendingKey($token));

        return $payload;
    }

    /**
     * Mint proof that the account owner just re-authenticated with Google.
     *
     * Bound to the user id: a ticket minted for one account can never confirm a
     * destructive action on another.
     */
    public function issueReauthToken(int $userId): string
    {
        $token = Str::random(64);

        Cache::put($this->reauthKey($token), $userId, self::REAUTH_TTL_SECONDS);

        return $token;
    }

    /**
     * Consume a re-authentication ticket. Returns false unless it exists AND was
     * minted for this very user.
     */
    public function consumeReauthToken(string $token, int $userId): bool
    {
        $storedUserId = $this->consumeOnce($this->reauthKey($token));

        return $storedUserId !== null && (int) $storedUserId === $userId;
    }

    /**
     * Read-and-burn a single-use entry.
     *
     * Cache::pull is get-then-forget: two concurrent requests can both read the
     * value before either forgets it. Cache::add is atomic (insertOrIgnore on the
     * database store, SET NX on Redis), so only one caller wins the `:spent` marker
     * and gets the value; the loser gets null.
     */
    private function consumeOnce(string $key): mixed
    {
        $value = Cache::get($key);

        if ($value === null) {
            return null;
        }

        if (! Cache::add($key.':spent', true, self::SPENT_MARKER_TTL_SECONDS)) {
            return null;
        }

        Cache::forget($key);

        return $value;
    }

    private function bindingFor(string $browserNonce): string
    {
        return hash('sha256', $browserNonce);
    }

    private function signingKey(): string
    {
        return (string) config('app.key');
    }

    private function stateKey(string $nonce): string
    {
        return 'oauth:state:'.$nonce;
    }

    private function exchangeKey(string $code): string
    {
        return 'oauth:xchg:'.hash('sha256', $code);
    }

    private function pendingKey(string $token): string
    {
        return 'oauth:pending:'.hash('sha256', $token);
    }

    private function reauthKey(string $token): string
    {
        return 'oauth:reauth:'.hash('sha256', $token);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
