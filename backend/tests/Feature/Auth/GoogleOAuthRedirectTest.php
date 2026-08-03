<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleOAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

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
        $response = $this->getJson('/api/v1/auth/google/redirect?intent=face');

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
            $this->getJson('/api/v1/auth/google/redirect?intent='.$intent)->assertOk();
        }
    }

    public function test_an_unknown_intent_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/google/redirect?intent=admin')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_INTENT');

        $this->getJson('/api/v1/auth/google/redirect')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_INTENT');
    }

    public function test_signup_intents_are_blocked_when_registration_is_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        foreach (['face', 'producer'] as $intent) {
            $this->getJson('/api/v1/auth/google/redirect?intent='.$intent)
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

        $this->getJson('/api/v1/auth/google/redirect?intent=login')->assertOk();
    }

    public function test_every_endpoint_is_closed_when_the_feature_flag_is_off(): void
    {
        config(['services.google.enabled' => false]);

        $this->getJson('/api/v1/auth/google/redirect?intent=face')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'GOOGLE_OAUTH_DISABLED');
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
            $this->getJson('/api/v1/auth/google/redirect?intent=login')->assertOk();
        }

        $this->getJson('/api/v1/auth/google/redirect?intent=login')->assertStatus(429);
    }
}
