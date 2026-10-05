<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Face;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Auth\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The account-resolution rules are the security core of the Google flow:
 * who gets logged in, who gets linked, and who gets nothing.
 */
class GoogleOAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const NONCE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_NONCE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private GoogleOAuthService $oauth;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        config([
            'services.google.enabled' => true,
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost:8000/api/v1/auth/google/callback',
            'app.frontend_url' => 'http://localhost:5173',
        ]);

        $this->oauth = app(GoogleOAuthService::class);
    }

    private function fakeGoogleUser(
        string $sub = 'google-sub-1',
        string $email = 'jean@gmail.com',
        bool $emailVerified = true,
    ): void {
        $socialiteUser = new SocialiteUser;
        $socialiteUser->id = $sub;
        $socialiteUser->email = $email;
        $socialiteUser->name = 'Jean Dupont';
        $socialiteUser->user = [
            'email_verified' => $emailVerified,
            'given_name' => 'Jean',
            'family_name' => 'Dupont',
        ];

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    /**
     * Follow the callback and pull the SPA-bound query back out of the redirect.
     *
     * @return array<string, string>
     */
    private function callbackQuery(string $state): array
    {
        $response = $this->get('/api/v1/auth/google/callback?state='.urlencode($state).'&code=google-auth-code');

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('http://localhost:5173/auth/google/callback?', (string) $location);

        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function exchange(string $code, string $nonce = self::NONCE): array
    {
        $response = $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => $nonce]);
        $response->assertOk();

        return $response->json('data');
    }

    private function makeFaceUser(string $email, ?string $googleId = null, bool $isActive = true): User
    {
        $face = Face::factory()->create();

        $user = User::create([
            'email' => $email,
            'password' => Hash::make('Password123'),
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'is_active' => $isActive,
        ]);

        if ($googleId !== null) {
            $user->forceFill(['google_id' => $googleId, 'google_linked_at' => now()])->save();
        }

        return $user;
    }

    public function test_a_brand_new_identity_creates_nothing_and_asks_for_completion(): void
    {
        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_FACE, null, self::NONCE));
        $data = $this->exchange($query['code']);

        $this->assertTrue($data['needs_completion']);
        $this->assertSame('jean@gmail.com', $data['email']);
        $this->assertSame('Jean', $data['prenom']);
        $this->assertSame('Dupont', $data['nom']);
        $this->assertSame('face', $data['intent']);
        $this->assertNotEmpty($data['pending_token']);

        // Nothing persisted yet: no role, no date of birth, no consent.
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('faces', 0);
    }

    public function test_a_known_google_identity_logs_straight_in(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE));
        $data = $this->exchange($query['code']);

        $this->assertFalse($data['needs_completion']);
        $this->assertSame($user->id, $data['user']['id']);
        $this->assertNotEmpty($data['token']);
    }

    public function test_a_matching_unverified_local_account_is_linked_then_secured(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com');
        $this->assertNull($user->google_id);
        $this->assertNull($user->email_verified_at);

        // A pre-registered squatter's credentials and sessions.
        $user->createToken('squatter');
        $this->assertSame(1, $user->tokens()->count());

        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE));
        $data = $this->exchange($query['code']);

        $this->assertFalse($data['needs_completion']);
        $this->assertSame($user->id, $data['user']['id']);
        $this->assertNotEmpty($data['token']);

        $user->refresh();
        $this->assertSame('google-sub-1', $user->google_id);
        $this->assertNotNull($user->google_linked_at);
        $this->assertNotNull($user->email_verified_at);
        // Whoever pre-registered the address no longer holds a way in.
        $this->assertNull($user->password);
        // Only the session minted by this very login survives.
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(0, $user->tokens()->where('name', 'squatter')->count());
    }

    public function test_a_matching_verified_local_account_keeps_its_password_and_tokens(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->createToken('existing');

        $this->fakeGoogleUser();

        $data = $this->exchange(
            $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE))['code']
        );

        $this->assertSame($user->id, $data['user']['id']);

        $user->refresh();
        $this->assertSame('google-sub-1', $user->google_id);
        $this->assertNotNull($user->password);
        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertSame(1, $user->tokens()->where('name', 'existing')->count());
    }

    public function test_an_account_already_linked_to_another_google_identity_is_never_overwritten(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-OTHER');
        $this->fakeGoogleUser(sub: 'google-sub-1');

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE));

        $this->assertSame('GOOGLE_ACCOUNT_CONFLICT', $query['error']);
        $this->assertArrayNotHasKey('code', $query);
        $this->assertSame('google-sub-OTHER', $user->refresh()->google_id);
    }

    /**
     * Step 1 (google_id) runs before step 2 (email_verified): a linked user still
     * logs in when the claim is missing.
     */
    public function test_a_user_linked_by_google_id_logs_in_even_when_the_email_claim_is_unverified(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        $this->fakeGoogleUser(emailVerified: false);

        $data = $this->exchange(
            $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE))['code']
        );

        $this->assertFalse($data['needs_completion']);
        $this->assertSame($user->id, $data['user']['id']);
    }

    public function test_a_code_minted_for_one_nonce_cannot_be_exchanged_with_another(): void
    {
        $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        $this->fakeGoogleUser();

        $code = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE))['code'];

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::OTHER_NONCE])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID')
            ->assertJsonPath('error.message', 'Lien de connexion expiré. Reprenez la connexion avec Google.');
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function codeKinds(): array
    {
        return [
            'authenticated' => [GoogleOAuthService::INTENT_LOGIN, true],
            'reauth' => [GoogleOAuthService::INTENT_REAUTH, true],
            'needs_completion' => [GoogleOAuthService::INTENT_FACE, false],
        ];
    }

    #[DataProvider('codeKinds')]
    public function test_a_nonce_binding_is_enforced_for_every_kind_of_code(string $intent, bool $knownUser): void
    {
        if ($knownUser) {
            $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        }
        $this->fakeGoogleUser();

        $code = $this->callbackQuery($this->oauth->issueState($intent, null, self::NONCE))['code'];

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::OTHER_NONCE])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    #[DataProvider('codeKinds')]
    public function test_the_right_nonce_exchanges_every_kind_of_code(string $intent, bool $knownUser): void
    {
        if ($knownUser) {
            $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        }
        $this->fakeGoogleUser();

        $data = $this->exchange($this->callbackQuery($this->oauth->issueState($intent, null, self::NONCE))['code']);

        match ($intent) {
            GoogleOAuthService::INTENT_LOGIN => $this->assertNotEmpty($data['token']),
            GoogleOAuthService::INTENT_REAUTH => $this->assertNotEmpty($data['reauth_token']),
            default => $this->assertTrue($data['needs_completion']),
        };
    }

    public function test_the_callback_mints_no_sanctum_token_and_never_puts_one_in_the_url(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        $this->fakeGoogleUser();

        $before = $user->tokens()->count();

        $response = $this->get('/api/v1/auth/google/callback?state='.urlencode(
            $this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE)
        ).'&code=google-auth-code');

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame(['code'], array_keys($query));
        $this->assertSame($before, $user->tokens()->count());

        $this->exchange($query['code']);

        $this->assertSame($before + 1, $user->tokens()->count());
    }

    public function test_a_user_deactivated_between_callback_and_exchange_gets_no_token(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        $this->fakeGoogleUser();

        $code = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE))['code'];

        $user->forceFill(['is_active' => false])->save();

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::NONCE])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_DEACTIVATED');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_backslash_redirect_is_dropped_before_it_is_signed(): void
    {
        $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');

        $state = $this->stateFromRedirectEndpoint('login', '/\\evil.com');
        $this->fakeGoogleUser();
        $data = $this->exchange($this->callbackQuery($state)['code']);

        $this->assertNull($data['redirect']);
    }

    public function test_a_state_and_an_exchange_code_are_consumed_atomically_once(): void
    {
        $code = $this->oauth->issueExchangeCode(['kind' => 'reauth']);

        $this->assertNotNull($this->oauth->consumeExchangeCode($code));
        $this->assertNull($this->oauth->consumeExchangeCode($code));

        $token = $this->oauth->issuePendingToken(['email' => 'x@y.z']);

        $this->assertNotNull($this->oauth->consumePendingToken($token));
        $this->assertNull($this->oauth->consumePendingToken($token));

        $ticket = $this->oauth->issueReauthToken(7);

        $this->assertTrue($this->oauth->consumeReauthToken($ticket, 7));
        $this->assertFalse($this->oauth->consumeReauthToken($ticket, 7));
    }

    /**
     * Without the claim, anyone controlling a Workspace domain could claim
     * someone else's address. This is the entire security basis for email linking.
     */
    public function test_an_unverified_google_email_never_links_and_never_creates(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com');
        $this->fakeGoogleUser(emailVerified: false);

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE));

        $this->assertSame('GOOGLE_EMAIL_UNVERIFIED', $query['error']);
        $this->assertArrayNotHasKey('code', $query);
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_a_missing_email_verified_claim_counts_as_unverified(): void
    {
        $this->makeFaceUser('jean@gmail.com');

        $socialiteUser = new SocialiteUser;
        $socialiteUser->id = 'google-sub-1';
        $socialiteUser->email = 'jean@gmail.com';
        $socialiteUser->name = 'Jean Dupont';
        $socialiteUser->user = []; // no email_verified key at all

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE));

        $this->assertSame('GOOGLE_EMAIL_UNVERIFIED', $query['error']);
    }

    public function test_google_is_not_a_way_around_deactivation(): void
    {
        $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1', isActive: false);
        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE));

        $this->assertSame('ACCOUNT_DEACTIVATED', $query['error']);
        $this->assertArrayNotHasKey('code', $query);
    }

    public function test_an_existing_user_still_signs_in_when_registration_is_closed(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE);
        config(['app.registration_enabled' => false]);

        $data = $this->exchange($this->callbackQuery($state)['code']);

        $this->assertFalse($data['needs_completion']);
        $this->assertSame($user->id, $data['user']['id']);
    }

    public function test_a_brand_new_account_is_refused_when_registration_is_closed(): void
    {
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE);
        config(['app.registration_enabled' => false]);

        $query = $this->callbackQuery($state);

        $this->assertSame('registration_disabled', $query['error']);
    }

    public function test_a_tampered_state_is_refused(): void
    {
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_FACE, null, self::NONCE);
        [$payload] = explode('.', $state, 2);

        $query = $this->callbackQuery($payload.'.deadbeef');

        $this->assertSame('OAUTH_STATE_INVALID', $query['error']);
    }

    public function test_a_replayed_state_is_refused(): void
    {
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_FACE, null, self::NONCE);

        $this->assertArrayHasKey('code', $this->callbackQuery($state));
        $this->assertSame('OAUTH_STATE_INVALID', $this->callbackQuery($state)['error']);
    }

    public function test_an_expired_state_is_refused(): void
    {
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_FACE, null, self::NONCE);

        $this->travel(11)->minutes();

        $this->assertSame('OAUTH_STATE_INVALID', $this->callbackQuery($state)['error']);
    }

    public function test_a_missing_state_is_refused(): void
    {
        $this->fakeGoogleUser();

        $response = $this->get('/api/v1/auth/google/callback?code=google-auth-code');
        $response->assertRedirect();

        $this->assertStringContainsString('error=OAUTH_STATE_INVALID', (string) $response->headers->get('Location'));
    }

    public function test_the_callback_is_closed_when_the_feature_flag_is_off(): void
    {
        $this->fakeGoogleUser();
        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_FACE, null, self::NONCE);

        config(['services.google.enabled' => false]);

        $this->assertSame('GOOGLE_OAUTH_DISABLED', $this->callbackQuery($state)['error']);
    }

    public function test_no_verification_mail_is_sent_on_the_google_path(): void
    {
        $this->makeFaceUser('jean@gmail.com');
        $this->fakeGoogleUser();

        $this->exchange($this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN, null, self::NONCE))['code']);

        Notification::assertNothingSentTo(User::query()->firstOrFail());
        Notification::assertNotSentTo(User::query()->firstOrFail(), VerifyEmailNotification::class);
    }

    public function test_reauth_returns_a_ticket_and_never_a_login_token(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        $this->fakeGoogleUser();

        $data = $this->exchange(
            $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_REAUTH, null, self::NONCE))['code']
        );

        $this->assertNotEmpty($data['reauth_token']);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('user', $data);

        // Bound to that user, and to no other.
        $this->assertTrue($this->oauth->consumeReauthToken($data['reauth_token'], $user->id));
    }

    /**
     * Re-authentication resolves by google_id ONLY: no email leap, no creation.
     */
    public function test_reauth_refuses_an_identity_that_is_not_linked(): void
    {
        $this->makeFaceUser('jean@gmail.com');
        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_REAUTH, null, self::NONCE));

        $this->assertSame('GOOGLE_ACCOUNT_NOT_LINKED', $query['error']);
        $this->assertArrayNotHasKey('code', $query);
    }

    public function test_reauth_creates_nothing_for_an_unknown_identity(): void
    {
        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_REAUTH, null, self::NONCE));

        $this->assertSame('GOOGLE_ACCOUNT_NOT_LINKED', $query['error']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reauth_refuses_a_deactivated_account(): void
    {
        $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1', isActive: false);
        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_REAUTH, null, self::NONCE));

        $this->assertSame('ACCOUNT_DEACTIVATED', $query['error']);
    }

    /**
     * Goes through the real /redirect endpoint: that is where a caller-supplied
     * `redirect` is sanitized before being sealed into the signed state.
     */
    private function stateFromRedirectEndpoint(string $intent, ?string $redirect = null): string
    {
        $url = '/api/v1/auth/google/redirect?intent='.$intent.'&nonce='.self::NONCE;

        if ($redirect !== null) {
            $url .= '&redirect='.urlencode($redirect);
        }

        $googleUrl = $this->getJson($url)->assertOk()->json('data.url');

        parse_str((string) parse_url((string) $googleUrl, PHP_URL_QUERY), $query);

        return (string) $query['state'];
    }

    public function test_a_valid_redirect_survives_the_round_trip(): void
    {
        $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');

        // Real Socialite driver first — the facade mock below has no expectations
        // for the redirect-building calls.
        $state = $this->stateFromRedirectEndpoint('login', '/face/dashboard');
        $this->fakeGoogleUser();
        $data = $this->exchange($this->callbackQuery($state)['code']);

        $this->assertSame('/face/dashboard', $data['redirect']);
    }

    public function test_a_protocol_relative_redirect_is_dropped_before_it_is_signed(): void
    {
        $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');

        // Real Socialite driver first — the facade mock below has no expectations
        // for the redirect-building calls.
        $state = $this->stateFromRedirectEndpoint('login', '//evil.com');
        $this->fakeGoogleUser();
        $data = $this->exchange($this->callbackQuery($state)['code']);

        $this->assertNull($data['redirect']);
    }

    public function test_an_absolute_redirect_is_dropped_before_it_is_signed(): void
    {
        $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');

        // Real Socialite driver first — the facade mock below has no expectations
        // for the redirect-building calls.
        $state = $this->stateFromRedirectEndpoint('login', 'https://evil.com/steal');
        $this->fakeGoogleUser();
        $data = $this->exchange($this->callbackQuery($state)['code']);

        $this->assertNull($data['redirect']);
    }
}
