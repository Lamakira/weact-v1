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
 * Behaviour of an account that has no password — the shape every account created
 * through an identity provider will have.
 */
class NullPasswordAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $passwordless;

    private User $withPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passwordless = $this->makeFaceUser('oauth@example.com', null);
        $this->withPassword = $this->makeFaceUser('classic@example.com', Hash::make('Password123'));
    }

    private function makeFaceUser(string $email, ?string $password): User
    {
        $face = Face::factory()->create();

        return User::create([
            'email' => $email,
            'password' => $password,
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
    }

    public function test_the_column_accepts_null_without_the_hashed_cast_interfering(): void
    {
        $this->assertNull($this->passwordless->fresh()->password);
    }

    public function test_setting_a_password_needs_no_current_password_when_there_is_none(): void
    {
        $response = $this->actingAs($this->passwordless)
            ->putJson('/api/v1/password', [
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
            ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('BrandNew123', $this->passwordless->fresh()->password));
    }

    public function test_changing_a_password_still_requires_the_current_one(): void
    {
        $response = $this->actingAs($this->withPassword)
            ->putJson('/api/v1/password', [
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('Password123', $this->withPassword->fresh()->password));
    }

    public function test_a_wrong_current_password_is_still_rejected(): void
    {
        $this->actingAs($this->withPassword)
            ->putJson('/api/v1/password', [
                'current_password' => 'NotTheOne123',
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_email_change_is_blocked_until_a_password_is_set(): void
    {
        $this->actingAs($this->passwordless)
            ->postJson('/api/v1/email/change', [
                'email' => 'new@example.com',
                'password' => 'anything',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'EMAIL_CHANGE_REQUIRES_PASSWORD');

        $this->assertNull($this->passwordless->fresh()->pending_email);
    }

    public function test_email_change_works_again_once_a_password_is_set(): void
    {
        $this->actingAs($this->passwordless)
            ->putJson('/api/v1/password', [
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
            ])
            ->assertOk();

        $this->actingAs($this->passwordless->fresh())
            ->postJson('/api/v1/email/change', [
                'email' => 'new@example.com',
                'password' => 'BrandNew123',
            ])
            ->assertOk();
    }

    public function test_account_deletion_points_at_reauthenticating_instead_of_failing_silently(): void
    {
        $this->actingAs($this->passwordless)
            ->deleteJson('/api/v1/user/account', ['password' => 'anything'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_DELETION_REQUIRES_REAUTH');

        $this->assertTrue($this->passwordless->fresh()->is_active);
        $this->assertSame('oauth@example.com', $this->passwordless->fresh()->email);
    }

    public function test_a_fresh_google_reauth_ticket_confirms_the_deletion(): void
    {
        $token = app(GoogleOAuthService::class)->issueReauthToken($this->passwordless->id);

        $this->actingAs($this->passwordless)
            ->deleteJson('/api/v1/user/account', ['reauth_token' => $token])
            ->assertOk();

        $this->assertFalse($this->passwordless->fresh()->is_active);
    }

    public function test_a_reauth_ticket_can_only_be_spent_once(): void
    {
        $token = app(GoogleOAuthService::class)->issueReauthToken($this->passwordless->id);

        $this->actingAs($this->passwordless)
            ->deleteJson('/api/v1/user/account', ['reauth_token' => $token])
            ->assertOk();

        $this->actingAs($this->passwordless->fresh())
            ->deleteJson('/api/v1/user/account', ['reauth_token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REAUTH_TOKEN_INVALID');
    }

    /**
     * The ticket is bound to the user it was minted for: a stolen bearer must not
     * be able to spend someone else's confirmation.
     */
    public function test_a_reauth_ticket_minted_for_another_account_is_refused(): void
    {
        $token = app(GoogleOAuthService::class)->issueReauthToken($this->withPassword->id);

        $this->actingAs($this->passwordless)
            ->deleteJson('/api/v1/user/account', ['reauth_token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REAUTH_TOKEN_INVALID');

        $this->assertTrue($this->passwordless->fresh()->is_active);
    }

    public function test_an_unknown_reauth_ticket_is_refused(): void
    {
        $this->actingAs($this->passwordless)
            ->deleteJson('/api/v1/user/account', ['reauth_token' => 'not-a-real-token'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REAUTH_TOKEN_INVALID');

        $this->assertTrue($this->passwordless->fresh()->is_active);
    }

    public function test_an_expired_reauth_ticket_is_refused(): void
    {
        $token = app(GoogleOAuthService::class)->issueReauthToken($this->passwordless->id);

        $this->travel(6)->minutes();

        $this->actingAs($this->passwordless)
            ->deleteJson('/api/v1/user/account', ['reauth_token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REAUTH_TOKEN_INVALID');
    }

    public function test_account_deletion_works_once_a_password_is_set(): void
    {
        $this->actingAs($this->passwordless)
            ->putJson('/api/v1/password', [
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
            ])
            ->assertOk();

        $this->actingAs($this->passwordless->fresh())
            ->deleteJson('/api/v1/user/account', ['password' => 'BrandNew123'])
            ->assertOk();

        $this->assertFalse($this->passwordless->fresh()->is_active);
    }

    public function test_user_resource_reports_whether_a_password_is_set(): void
    {
        $this->actingAs($this->passwordless)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.has_password', false);

        $this->actingAs($this->withPassword)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.has_password', true);
    }
}
