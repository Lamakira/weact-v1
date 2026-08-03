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

    /** @var list<string> */
    public const INTENTS = [self::INTENT_FACE, self::INTENT_PRODUCER, self::INTENT_LOGIN];

    private const STATE_TTL_SECONDS = 600;

    private const EXCHANGE_TTL_SECONDS = 120;

    private const PENDING_TTL_SECONDS = 900;

    /**
     * Build a tamper-proof, single-use `state`.
     *
     * Socialite's stateful mode keeps state in the session, which would have to
     * survive SPA(:5173) → API(:8000) → accounts.google.com → API(:8000) with
     * SameSite=lax across three origins — and, decisively, session state cannot
     * carry the intent. Google enforces an exact-match redirect_uri, so the intent
     * cannot ride there either.
     */
    public function issueState(string $intent, ?string $redirect = null): string
    {
        $nonce = Str::random(32);

        $payload = json_encode([
            'intent' => $intent,
            'redirect' => $redirect,
            'nonce' => $nonce,
            'exp' => now()->addSeconds(self::STATE_TTL_SECONDS)->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        Cache::put($this->stateKey($nonce), true, self::STATE_TTL_SECONDS);

        return $this->base64UrlEncode($payload).'.'.hash_hmac('sha256', $payload, $this->signingKey());
    }

    /**
     * Verify and consume a `state`.
     *
     * @return array{intent: string, redirect: string|null}|null null on a tampered,
     *                                                           expired or replayed state
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
        if (Cache::pull($this->stateKey($nonce)) === null) {
            return null;
        }

        $redirect = $decoded['redirect'] ?? null;

        return [
            'intent' => $intent,
            'redirect' => is_string($redirect) ? $redirect : null,
        ];
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
        $payload = Cache::pull($this->exchangeKey($code));

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
        $payload = Cache::pull($this->pendingKey($token));

        return $payload;
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
