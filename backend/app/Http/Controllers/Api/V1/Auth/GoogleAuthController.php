<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteGoogleRegistrationRequest;
use App\Http\Requests\Auth\GoogleExchangeRequest;
use App\Http\Requests\Auth\GoogleRedirectRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\FaceRegistrationService;
use App\Services\Auth\GoogleAccountLinker;
use App\Services\Auth\GoogleOAuthService;
use App\Services\Auth\ProducerRegistrationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

/**
 * Google Sign-In.
 *
 * Shape of the flow, and why:
 *
 *  1. GET  /auth/google/redirect  returns the Google URL as JSON and lets the SPA
 *     navigate. A 302 would be followed by the XHR itself, and Google's response
 *     carries no Access-Control-Allow-Origin — the fetch dies and the top-level
 *     document never moves. config/cors.php only covers our own first hop.
 *  2. GET  /auth/google/callback  is a top-level browser navigation, not an XHR:
 *     no Origin header, so CORS does not apply and cors.php needs no change. It
 *     resolves the account and redirects to the SPA with a one-shot code — no
 *     token is minted here.
 *  3. POST /auth/google/exchange  trades that code (plus the browser-held nonce the
 *     flow started with) for the token, minted at that point. The token never
 *     travels in a URL: it is a 30-day bearer stored in localStorage, and a URL
 *     would write it into history, Referer headers and access logs.
 *  4. POST /auth/google/complete-registration  finishes a brand-new account —
 *     Google returns no role, no date of birth and no consent.
 */
class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
        private readonly GoogleAccountLinker $linker,
        private readonly FaceRegistrationService $faceRegistration,
        private readonly ProducerRegistrationService $producerRegistration,
    ) {}

    /**
     * Hand the SPA the Google authorization URL.
     */
    public function redirect(GoogleRedirectRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $intent = $validated['intent'];

        // Cheap early exit that mirrors the "inscriptions suspendues" panel both
        // register pages already render. Only the signup intents are blocked: an
        // EXISTING user must still be able to log in (`login`) or confirm an
        // erasure (`reauth`) when registration is closed.
        if (
            in_array($intent, [GoogleOAuthService::INTENT_FACE, GoogleOAuthService::INTENT_PRODUCER], true)
            && ! config('app.registration_enabled', true)
        ) {
            return $this->error(
                'registration_disabled',
                'Les inscriptions sont temporairement suspendues. Veuillez réessayer ultérieurement.',
                403
            );
        }

        $redirect = $validated['redirect'] ?? null;
        $redirect = is_string($redirect) ? $redirect : null;

        $url = $this->googleProvider()
            ->stateless()
            ->with($this->authorizationParameters($intent, $this->safeRedirect($redirect), $validated['nonce']))
            ->redirect()
            ->getTargetUrl();

        return response()->json(['data' => ['url' => $url]]);
    }

    /**
     * Extra query parameters of the Google authorization URL.
     *
     * Re-authentication must need a click: Google supports neither `max_age` nor
     * `prompt=login` (only none / consent / select_account), so `select_account` is
     * the strongest available — it stops a hijacked session from silently reusing
     * the browser's Google session. Sign-in intents are left alone.
     *
     * @return array<string, string>
     */
    private function authorizationParameters(string $intent, ?string $redirect, string $nonce): array
    {
        $parameters = ['state' => $this->oauth->issueState($intent, $redirect, $nonce)];

        if ($intent === GoogleOAuthService::INTENT_REAUTH) {
            $parameters['prompt'] = 'select_account';
        }

        return $parameters;
    }

    /**
     * Google sends the browser back here.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! (bool) config('services.google.enabled', false)) {
            return $this->bounce(['error' => 'GOOGLE_OAUTH_DISABLED']);
        }

        $state = $this->oauth->consumeState($request->query('state'));

        if ($state === null) {
            // Tampered, expired or replayed.
            return $this->bounce(['error' => 'OAUTH_STATE_INVALID']);
        }

        try {
            $googleUser = $this->googleProvider()->stateless()->user();
        } catch (\Throwable $e) {
            Log::warning('auth.google.callback_failed', ['message' => $e->getMessage()]);

            return $this->bounce(['error' => 'GOOGLE_HANDSHAKE_FAILED']);
        }

        $identity = $this->identityFrom($googleUser);

        if ($identity === null) {
            return $this->bounce(['error' => 'GOOGLE_HANDSHAKE_FAILED']);
        }

        if ($state['intent'] === GoogleOAuthService::INTENT_REAUTH) {
            return $this->bounce($this->reauthQuery($identity, $state['redirect'], $state['binding']));
        }

        $result = $this->linker->resolve($identity, $state['intent']);

        if ($result['outcome'] === GoogleAccountLinker::OUTCOME_ERROR) {
            return $this->bounce(['error' => $result['code']]);
        }

        if ($result['outcome'] === GoogleAccountLinker::OUTCOME_AUTHENTICATED) {
            /** @var User $user */
            $user = $result['user'];

            // No Sanctum token yet: it is minted by exchange(), once the browser
            // binding has been checked.
            $code = $this->oauth->issueExchangeCode([
                'kind' => 'authenticated',
                'user_id' => $user->id,
                'redirect' => $state['redirect'],
                'binding' => $state['binding'],
            ]);

            return $this->bounce(['code' => $code]);
        }

        // Brand-new account: nothing created yet.
        $code = $this->oauth->issueExchangeCode([
            'kind' => 'needs_completion',
            'pending_token' => $this->oauth->issuePendingToken($result['profile']),
            'profile' => $result['profile'],
            'redirect' => $state['redirect'],
            'binding' => $state['binding'],
        ]);

        return $this->bounce(['code' => $code]);
    }

    /**
     * Trade the one-shot code for the token (or for the finalisation payload).
     */
    public function exchange(GoogleExchangeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payload = $this->oauth->consumeExchangeCode($validated['code']);

        // Same answer for an unknown code and a nonce mismatch: do not reveal
        // which check failed.
        if ($payload === null || ! $this->oauth->bindingMatches($payload['binding'] ?? null, $validated['nonce'])) {
            return $this->codeInvalid();
        }

        if ($payload['kind'] === 'reauth') {
            return response()->json([
                'data' => [
                    'reauth_token' => $payload['reauth_token'],
                    'redirect' => $payload['redirect'],
                ],
            ]);
        }

        if ($payload['kind'] === 'needs_completion') {
            return response()->json([
                'data' => [
                    'needs_completion' => true,
                    'pending_token' => $payload['pending_token'],
                    'email' => $payload['profile']['email'],
                    'prenom' => $payload['profile']['prenom'],
                    'nom' => $payload['profile']['nom'],
                    'intent' => $payload['profile']['intent'],
                    'redirect' => $payload['redirect'],
                ],
            ]);
        }

        $user = User::query()->with('userable')->find($payload['user_id']);

        if ($user === null) {
            return $this->codeInvalid();
        }

        // The account may have been deactivated between the callback and now.
        if (! $user->is_active) {
            return $this->error('ACCOUNT_DEACTIVATED', 'Ce compte a été désactivé.', 403);
        }

        return response()->json([
            'data' => [
                'needs_completion' => false,
                'user' => UserResource::forOwner($user),
                'token' => $user->createToken('auth-token')->plainTextToken,
                'redirect' => $payload['redirect'],
            ],
        ]);
    }

    /**
     * Create the account the finalisation screen just described.
     */
    public function completeRegistration(CompleteGoogleRegistrationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $profile = $this->oauth->consumePendingToken($validated['pending_token']);

        if ($profile === null) {
            return $this->error('OAUTH_PENDING_INVALID', 'Session expirée. Reprenez la connexion avec Google.', 422);
        }

        // The address may have been claimed between the callback and this call.
        if (User::query()->where('email', $profile['email'])->exists()) {
            return $this->error('EMAIL_ALREADY_USED', 'Cet email est déjà utilisé.', 422);
        }

        try {
            $result = $validated['role'] === 'face'
                ? $this->faceRegistration->registerFromGoogle([
                    'nom' => $validated['nom'],
                    'prenom' => $validated['prenom'],
                    'email' => $profile['email'],
                    'date_naissance' => $validated['date_naissance'],
                ], $profile['google_id'], $request->ip())
                : $this->producerRegistration->registerFromGoogle([
                    'type' => $validated['type'],
                    'email' => $profile['email'],
                    'agency_name' => $validated['agency_name'] ?? null,
                    'nom_complet' => $validated['nom_complet'] ?? null,
                ], $profile['google_id'], $request->ip());
        } catch (UniqueConstraintViolationException) {
            // The email (or google_id) was taken between the exists() check above
            // and the insert: a race, not a server fault.
            return $this->error('EMAIL_ALREADY_USED', 'Cet email est déjà utilisé.', 422);
        }

        return response()->json([
            'data' => [
                'user' => UserResource::forOwner($result['user']),
                'token' => $result['token'],
            ],
            'message' => 'Inscription réussie',
        ], 201);
    }

    /**
     * Re-authentication before an irreversible action.
     *
     * Resolves by `google_id` ONLY: no email leap, no account creation, and no
     * login token — this path proves ownership, it does not open a session. The
     * ticket is bound to the user id, so a stolen bearer cannot mint one for a
     * victim without also controlling their Google account.
     *
     * @param  array{sub: string, email: string, email_verified: bool, given_name: string|null, family_name: string|null, name: string|null}  $identity
     * @return array<string, string|null>
     */
    private function reauthQuery(array $identity, ?string $redirect, ?string $binding): array
    {
        $user = User::query()->where('google_id', $identity['sub'])->first();

        if ($user === null) {
            return ['error' => 'GOOGLE_ACCOUNT_NOT_LINKED'];
        }

        if (! $user->is_active) {
            return ['error' => 'ACCOUNT_DEACTIVATED'];
        }

        return [
            'code' => $this->oauth->issueExchangeCode([
                'kind' => 'reauth',
                'reauth_token' => $this->oauth->issueReauthToken($user->id),
                'redirect' => $redirect,
                'binding' => $binding,
            ]),
        ];
    }

    /**
     * `Socialite::driver()` is typed to the Provider contract, which has no
     * stateless(). Narrowing here keeps the concrete type without a cast, and the
     * guard is real: a misconfigured driver would otherwise fail deeper in, on a
     * request that has already left for Google.
     */
    private function googleProvider(): GoogleProvider
    {
        $driver = Socialite::driver('google');

        if (! $driver instanceof GoogleProvider) {
            throw new \RuntimeException('The Google Socialite driver is misconfigured.');
        }

        return $driver;
    }

    /**
     * Normalize Socialite's user into the shape the linker consumes.
     *
     * @return array{sub: string, email: string, email_verified: bool, given_name: string|null, family_name: string|null, name: string|null}|null
     */
    private function identityFrom(mixed $googleUser): ?array
    {
        $sub = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (! is_string($sub) || $sub === '' || ! is_string($email) || $email === '') {
            return null;
        }

        $raw = is_array($googleUser->user) ? $googleUser->user : [];

        return [
            'sub' => $sub,
            'email' => $email,
            // Strict comparison on purpose: a missing claim is NOT a verified email.
            'email_verified' => ($raw['email_verified'] ?? null) === true,
            'given_name' => isset($raw['given_name']) && is_string($raw['given_name']) ? $raw['given_name'] : null,
            'family_name' => isset($raw['family_name']) && is_string($raw['family_name']) ? $raw['family_name'] : null,
            'name' => is_string($googleUser->getName()) ? $googleUser->getName() : null,
        ];
    }

    /**
     * Redirect back to the SPA callback route.
     *
     * @param  array<string, string|null>  $query
     */
    private function bounce(array $query): RedirectResponse
    {
        $base = rtrim((string) config('app.frontend_url'), '/').'/auth/google/callback';

        return redirect()->away($base.'?'.http_build_query(array_filter($query, fn ($v) => $v !== null)));
    }

    /**
     * Only same-origin absolute paths survive: `//evil.com`, full URLs and anything
     * with a backslash (`/\evil.com` — browsers read `\` as `/`) are dropped.
     */
    private function safeRedirect(?string $redirect): ?string
    {
        if (
            $redirect === null
            || ! str_starts_with($redirect, '/')
            || str_starts_with($redirect, '//')
            || str_contains($redirect, '\\')
        ) {
            return null;
        }

        return $redirect;
    }

    private function codeInvalid(): JsonResponse
    {
        return $this->error('OAUTH_CODE_INVALID', 'Lien de connexion expiré. Reprenez la connexion avec Google.', 422);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
