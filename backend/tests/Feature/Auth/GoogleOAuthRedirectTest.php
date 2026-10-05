<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class GoogleOAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    private const NONCE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.enabled' => true,
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost:8000/api/v1/auth/google/callback',
        ]);
    }

    public function test_it_returns_a_google_authorization_url_carrying_our_state(): void
    {
        $response = $this->getJson('/api/v1/auth/google/redirect?intent=face&nonce='.self::NONCE);

        $response->assertOk()->assertJsonStructure(['data' => ['url']]);

        $url = $response->json('data.url');

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('state', $query);
        // Signed payload: base64url body, dot, HMAC.
        $this->assertStringContainsString('.', $query['state']);
    }

    public function test_each_entry_point_is_accepted(): void
    {
        foreach (['face', 'producer', 'login'] as $intent) {
            $this->getJson('/api/v1/auth/google/redirect?intent='.$intent.'&nonce='.self::NONCE)->assertOk();
        }
    }

    /**
     * Google has no `max_age` / `prompt=login`: `select_account` is the only way to
     * make a re-authentication need a click instead of silently reusing the session.
     */
    public function test_only_the_reauth_intent_forces_the_account_chooser(): void
    {
        $reauth = $this->getJson('/api/v1/auth/google/redirect?intent=reauth&nonce='.self::NONCE)
            ->assertOk()
            ->json('data.url');

        parse_str((string) parse_url($reauth, PHP_URL_QUERY), $query);
        $this->assertSame('select_account', $query['prompt'] ?? null);
        $this->assertArrayHasKey('state', $query);

        foreach (['login', 'face', 'producer'] as $intent) {
            $url = $this->getJson('/api/v1/auth/google/redirect?intent='.$intent.'&nonce='.self::NONCE)
                ->assertOk()
                ->json('data.url');

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $this->assertArrayNotHasKey('prompt', $query, $intent);
        }
    }

    public function test_an_unknown_intent_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/google/redirect?intent=admin&nonce='.self::NONCE)
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['intent']]]);

        $this->getJson('/api/v1/auth/google/redirect?nonce='.self::NONCE)
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['intent']]]);
    }

    public function test_the_nonce_is_required_and_must_be_url_safe_and_long_enough(): void
    {
        $this->getJson('/api/v1/auth/google/redirect?intent=login')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['nonce']]]);

        // Too short (42), too long (129), forbidden characters.
        foreach ([str_repeat('a', 42), str_repeat('a', 129), str_repeat('a', 42).'+'] as $nonce) {
            $this->getJson('/api/v1/auth/google/redirect?intent=login&nonce='.urlencode($nonce))
                ->assertStatus(422)
                ->assertJsonStructure(['error' => ['details' => ['nonce']]]);
        }

        $this->getJson('/api/v1/auth/google/redirect?intent=login&nonce='.str_repeat('a', 128))->assertOk();
        $this->getJson('/api/v1/auth/google/redirect?intent=login&nonce='.str_repeat('A-_', 15))->assertOk();
    }

    public function test_signup_intents_are_blocked_when_registration_is_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        foreach (['face', 'producer'] as $intent) {
            $this->getJson('/api/v1/auth/google/redirect?intent='.$intent.'&nonce='.self::NONCE)
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'registration_disabled');
        }
    }

    /**
     * Closing registration must not lock existing users out of their account.
     */
    public function test_the_login_intent_still_works_when_registration_is_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->getJson('/api/v1/auth/google/redirect?intent=login&nonce='.self::NONCE)->assertOk();
    }

    /**
     * Re-authentication is not a signup: a user must still be able to confirm an
     * erasure (Art. 443) while registration is closed.
     */
    public function test_the_reauth_intent_still_works_when_registration_is_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->getJson('/api/v1/auth/google/redirect?intent=reauth&nonce='.self::NONCE)->assertOk();
    }

    public function test_every_endpoint_is_closed_when_the_feature_flag_is_off(): void
    {
        config(['services.google.enabled' => false]);

        // Unnamed throttles share one counter per IP: this test makes more calls than
        // the strictest limit allows and is about the flag, not the limiter.
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->getJson('/api/v1/auth/google/redirect?intent=face&nonce='.self::NONCE)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'GOOGLE_OAUTH_DISABLED');

        $this->postJson('/api/v1/auth/google/exchange', ['code' => 'x', 'nonce' => self::NONCE])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'GOOGLE_OAUTH_DISABLED');

        $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => 'x',
            'role' => 'face',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '1995-06-15',
            'accept_cgu' => true,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'GOOGLE_OAUTH_DISABLED');

        // The flag check precedes validation: a malformed body must not get a 422
        // (and with it the shape of the endpoint) while the feature is off.
        $this->getJson('/api/v1/auth/google/redirect?intent=nope')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'GOOGLE_OAUTH_DISABLED');

        $this->postJson('/api/v1/auth/google/exchange', [])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'GOOGLE_OAUTH_DISABLED');

        $this->postJson('/api/v1/auth/google/complete-registration', [])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'GOOGLE_OAUTH_DISABLED');

        $response = $this->get('/api/v1/auth/google/callback?state=x&code=y');
        $response->assertRedirect();
        $this->assertStringContainsString('error=GOOGLE_OAUTH_DISABLED', (string) $response->headers->get('Location'));
    }

    public function test_the_registration_status_probe_advertises_the_google_flag(): void
    {
        $this->getJson('/api/v1/auth/registration-status')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.google_enabled', true);

        config(['services.google.enabled' => false]);

        $this->getJson('/api/v1/auth/registration-status')
            ->assertOk()
            ->assertJsonPath('data.google_enabled', false);
    }

    public function test_the_redirect_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/auth/google/redirect?intent=login&nonce='.self::NONCE)->assertOk();
        }

        $this->getJson('/api/v1/auth/google/redirect?intent=login&nonce='.self::NONCE)->assertStatus(429);
    }
}
