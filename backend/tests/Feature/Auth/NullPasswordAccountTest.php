<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\Auth\GoogleOAuthService;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
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

    private function ticketFor(User $user): string
    {
        return app(GoogleOAuthService::class)->issueReauthToken($user->id);
    }

    public function test_setting_a_first_password_needs_a_reauth_ticket_but_no_current_password(): void
    {
        // `current_password: null` is what the SPA sends (ConvertEmptyStringsToNull).
        $response = $this->actingAs($this->passwordless)
            ->putJson('/api/v1/password', [
                'current_password' => null,
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
                'reauth_token' => $this->ticketFor($this->passwordless),
            ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('BrandNew123', $this->passwordless->fresh()->password));
    }

    public function test_setting_a_first_password_without_a_ticket_is_refused(): void
    {
        $this->actingAs($this->passwordless)
            ->putJson('/api/v1/password', [
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reauth_token']);

        $this->assertNull($this->passwordless->fresh()->password);
    }

    public function test_a_ticket_minted_for_another_account_cannot_set_a_first_password(): void
    {
        $this->actingAs($this->passwordless)
            ->putJson('/api/v1/password', [
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
                'reauth_token' => $this->ticketFor($this->withPassword),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REAUTH_TOKEN_INVALID')
            ->assertJsonPath('error.message', 'Confirmation expirée. Reprenez la confirmation avec Google.');

        $this->assertNull($this->passwordless->fresh()->password);
    }

    public function test_the_ticket_for_a_first_password_is_single_use(): void
    {
        $ticket = $this->ticketFor($this->passwordless);
        $payload = [
            'new_password' => 'BrandNew123',
            'new_password_confirmation' => 'BrandNew123',
            'reauth_token' => $ticket,
        ];

        $this->actingAs($this->passwordless)->putJson('/api/v1/password', $payload)->assertOk();

        // Back to a password-less state, same ticket replayed.
        User::query()->whereKey($this->passwordless->id)->update(['password' => null]);

        $this->actingAs($this->passwordless->fresh())
            ->putJson('/api/v1/password', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REAUTH_TOKEN_INVALID');

        $this->assertNull($this->passwordless->fresh()->password);
    }

    public function test_the_reauth_ticket_is_ignored_when_the_account_has_a_password(): void
    {
        $this->actingAs($this->withPassword)
            ->putJson('/api/v1/password', [
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
                'reauth_token' => $this->ticketFor($this->withPassword),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
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
                'reauth_token' => $this->ticketFor($this->passwordless),
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

    public function test_account_deletion_with_an_empty_body_also_points_at_reauthenticating(): void
    {
        $this->actingAs($this->passwordless)
            ->deleteJson('/api/v1/user/account')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_DELETION_REQUIRES_REAUTH');

        $this->assertTrue($this->passwordless->fresh()->is_active);
    }

    public function test_account_deletion_with_an_empty_body_still_asks_for_the_password_when_there_is_one(): void
    {
        $this->actingAs($this->withPassword)
            ->deleteJson('/api/v1/user/account')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['password']]]);

        $this->assertTrue($this->withPassword->fresh()->is_active);
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
                'reauth_token' => $this->ticketFor($this->passwordless),
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

    /**
     * `has_password` means "signs in with Google only": it must not reach the other
     * party of a booking, who is rendered through the same UserResource.
     */
    public function test_has_password_is_not_leaked_to_the_other_party_of_a_booking(): void
    {
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->passwordless->id,
            'producer_id' => $producerUser->id,
        ]);

        $asFace = $this->actingAs($this->passwordless)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk();

        $this->assertArrayNotHasKey('has_password', $asFace->json('data.producer'));
        // Their own account still carries it.
        $this->assertArrayHasKey('has_password', $asFace->json('data.face'));

        $asProducer = $this->actingAs($producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk();

        // The Face's sign-in method stays private from the Producer too.
        $this->assertArrayNotHasKey('has_password', $asProducer->json('data.face'));
        $this->assertArrayHasKey('has_password', $asProducer->json('data.producer'));
    }

    public function test_has_password_is_present_on_unauthenticated_auth_responses(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'classic@example.com',
            'password' => 'Password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.has_password', true);
    }

    public function test_a_failing_password_changed_mail_does_not_fail_the_password_change(): void
    {
        $this->app->instance(NotificationDispatcher::class, new class implements NotificationDispatcher
        {
            public function send($notifiables, $notification): void
            {
                throw new \RuntimeException('SMTP down');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null): void
            {
                throw new \RuntimeException('SMTP down');
            }
        });

        $ticket = $this->ticketFor($this->passwordless);

        $this->actingAs($this->passwordless)
            ->putJson('/api/v1/password', [
                'new_password' => 'BrandNew123',
                'new_password_confirmation' => 'BrandNew123',
                'reauth_token' => $ticket,
            ])
            ->assertOk()
            ->assertJsonPath('data.password_changed', true);

        $this->assertTrue(Hash::check('BrandNew123', $this->passwordless->fresh()->password));
    }

    public function test_the_data_export_reports_the_authentication_methods(): void
    {
        $this->passwordless->forceFill(['google_id' => 'google-sub-1', 'google_linked_at' => now()])->save();

        $linked = $this->actingAs($this->passwordless->fresh())
            ->getJson('/api/v1/user/data-export')
            ->assertOk()
            ->assertJsonPath('data.account.has_password', false)
            ->assertJsonPath('data.account.google_linked', true);

        $this->assertNotNull($linked->json('data.account.google_linked_at'));

        $this->actingAs($this->withPassword)
            ->getJson('/api/v1/user/data-export')
            ->assertOk()
            ->assertJsonPath('data.account.has_password', true)
            ->assertJsonPath('data.account.google_linked', false)
            ->assertJsonPath('data.account.google_linked_at', null);
    }
}
