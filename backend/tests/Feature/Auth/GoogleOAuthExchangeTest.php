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

    private GoogleOAuthService $oauth;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

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
            'token' => $this->user->createToken('auth-token')->plainTextToken,
            'redirect' => null,
        ]);
    }

    public function test_the_returned_token_authenticates_the_user(): void
    {
        $code = $this->issueAuthenticatedCode();

        $token = $this->postJson('/api/v1/auth/google/exchange', ['code' => $code])
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

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code])->assertOk();

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = $this->issueAuthenticatedCode();

        $this->travel(3)->minutes();

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_an_unknown_code_is_refused(): void
    {
        $this->postJson('/api/v1/auth/google/exchange', ['code' => 'not-a-real-code'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_the_code_is_required(): void
    {
        $this->postJson('/api/v1/auth/google/exchange', [])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['code']]]);
    }

    public function test_a_code_pointing_at_a_vanished_user_is_refused(): void
    {
        $code = $this->issueAuthenticatedCode();

        $this->user->tokens()->delete();
        $this->user->delete();

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OAUTH_CODE_INVALID');
    }

    public function test_the_exchange_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/auth/google/exchange', ['code' => 'nope'])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/google/exchange', ['code' => 'nope'])->assertStatus(429);
    }
}
