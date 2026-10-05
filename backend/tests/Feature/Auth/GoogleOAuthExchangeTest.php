<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Face;
use App\Models\User;
use App\Services\Auth\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The exchange code is what keeps the 30-day bearer out of the URL. It must be
 * single-use and short-lived, or it becomes a second credential.
 */
class GoogleOAuthExchangeTest extends TestCase
{
    use RefreshDatabase;

    private const NONCE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private GoogleOAuthService $oauth;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.enabled' => true]);

        $this->oauth = app(GoogleOAuthService::class);

        $face = Face::factory()->create();
        $this->user = User::create([
            'email' => 'jean@gmail.com',
            'password' => Hash::make('Password123'),
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
    }

    private function issueAuthenticatedCode(): string
    {
        return $this->oauth->issueExchangeCode([
            'kind' => 'authenticated',
            'user_id' => $this->user->id,
            'redirect' => null,
            'binding' => hash('sha256', self::NONCE),
        ]);
    }

    public function test_the_returned_token_authenticates_the_user(): void
    {
        $code = $this->issueAuthenticatedCode();

        $token = $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::NONCE])
            ->assertOk()
            ->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'jean@gmail.com');
    }

    public function test_a_code_can_only_be_spent_once(): void
    {
        $code = $this->issueAuthenticatedCode();

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::NONCE])->assertOk();

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::NONCE])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = $this->issueAuthenticatedCode();

        $this->travel(3)->minutes();

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::NONCE])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_an_unknown_code_is_refused(): void
    {
        $this->postJson('/api/v1/auth/google/exchange', ['code' => 'not-a-real-code', 'nonce' => self::NONCE])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_the_code_is_required(): void
    {
        $this->postJson('/api/v1/auth/google/exchange', ['nonce' => self::NONCE])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['code']]]);
    }

    public function test_the_nonce_is_required(): void
    {
        $this->postJson('/api/v1/auth/google/exchange', ['code' => $this->issueAuthenticatedCode()])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['nonce']]]);
    }

    public function test_a_code_without_a_binding_is_refused(): void
    {
        $code = $this->oauth->issueExchangeCode([
            'kind' => 'authenticated',
            'user_id' => $this->user->id,
            'redirect' => null,
        ]);

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::NONCE])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_a_code_pointing_at_a_vanished_user_is_refused(): void
    {
        $code = $this->issueAuthenticatedCode();

        $this->user->tokens()->delete();
        $this->user->delete();

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code, 'nonce' => self::NONCE])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_the_exchange_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/auth/google/exchange', ['code' => 'nope', 'nonce' => self::NONCE])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/google/exchange', ['code' => 'nope', 'nonce' => self::NONCE])->assertStatus(429);
    }
}
