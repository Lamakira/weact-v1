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
use Tests\TestCase;

/**
 * The account-resolution rules are the security core of the Google flow:
 * who gets logged in, who gets linked, and who gets nothing.
 */
class GoogleOAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

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
    private function exchange(string $code): array
    {
        $response = $this->postJson('/api/v1/auth/google/exchange', ['code' => $code]);
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

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_FACE));
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

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN));
        $data = $this->exchange($query['code']);

        $this->assertFalse($data['needs_completion']);
        $this->assertSame($user->id, $data['user']['id']);
        $this->assertNotEmpty($data['token']);
    }

    public function test_a_matching_local_account_is_linked_when_google_attests_the_address(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com');
        $this->assertNull($user->google_id);
        $this->assertNull($user->email_verified_at);

        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN));
        $data = $this->exchange($query['code']);

        $this->assertFalse($data['needs_completion']);
        $this->assertSame($user->id, $data['user']['id']);

        $user->refresh();
        $this->assertSame('google-sub-1', $user->google_id);
        $this->assertNotNull($user->google_linked_at);
        // Google already attested the address — our own mail would prove the same
        // fact with weaker assurance.
        $this->assertNotNull($user->email_verified_at);
    }

    /**
     * Without the claim, anyone controlling a Workspace domain could claim
     * someone else's address. This is the entire security basis for email linking.
     */
    public function test_an_unverified_google_email_never_links_and_never_creates(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com');
        $this->fakeGoogleUser(emailVerified: false);

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN));

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

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN));

        $this->assertSame('GOOGLE_EMAIL_UNVERIFIED', $query['error']);
    }

    public function test_google_is_not_a_way_around_deactivation(): void
    {
        $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1', isActive: false);
        $this->fakeGoogleUser();

        $query = $this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN));

        $this->assertSame('ACCOUNT_DEACTIVATED', $query['error']);
        $this->assertArrayNotHasKey('code', $query);
    }

    public function test_an_existing_user_still_signs_in_when_registration_is_closed(): void
    {
        $user = $this->makeFaceUser('jean@gmail.com', googleId: 'google-sub-1');
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN);
        config(['app.registration_enabled' => false]);

        $data = $this->exchange($this->callbackQuery($state)['code']);

        $this->assertFalse($data['needs_completion']);
        $this->assertSame($user->id, $data['user']['id']);
    }

    public function test_a_brand_new_account_is_refused_when_registration_is_closed(): void
    {
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN);
        config(['app.registration_enabled' => false]);

        $query = $this->callbackQuery($state);

        $this->assertSame('registration_disabled', $query['error']);
    }

    public function test_a_tampered_state_is_refused(): void
    {
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_FACE);
        [$payload] = explode('.', $state, 2);

        $query = $this->callbackQuery($payload.'.deadbeef');

        $this->assertSame('OAUTH_STATE_INVALID', $query['error']);
    }

    public function test_a_replayed_state_is_refused(): void
    {
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_FACE);

        $this->assertArrayHasKey('code', $this->callbackQuery($state));
        $this->assertSame('OAUTH_STATE_INVALID', $this->callbackQuery($state)['error']);
    }

    public function test_an_expired_state_is_refused(): void
    {
        $this->fakeGoogleUser();

        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_FACE);

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
        $state = $this->oauth->issueState(GoogleOAuthService::INTENT_FACE);

        config(['services.google.enabled' => false]);

        $this->assertSame('GOOGLE_OAUTH_DISABLED', $this->callbackQuery($state)['error']);
    }

    public function test_no_verification_mail_is_sent_on_the_google_path(): void
    {
        $this->makeFaceUser('jean@gmail.com');
        $this->fakeGoogleUser();

        $this->exchange($this->callbackQuery($this->oauth->issueState(GoogleOAuthService::INTENT_LOGIN))['code']);

        Notification::assertNothingSentTo(User::query()->firstOrFail());
        Notification::assertNotSentTo(User::query()->firstOrFail(), VerifyEmailNotification::class);
    }

    /**
     * Goes through the real /redirect endpoint: that is where a caller-supplied
     * `redirect` is sanitized before being sealed into the signed state.
     */
    private function stateFromRedirectEndpoint(string $intent, ?string $redirect = null): string
    {
        $url = '/api/v1/auth/google/redirect?intent='.$intent;

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
