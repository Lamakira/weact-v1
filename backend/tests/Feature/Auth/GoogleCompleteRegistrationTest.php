<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Auth\GoogleOAuthService;
use App\Services\Auth\UsernameGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Google returns neither a role, nor a date of birth, nor consent — so every
 * Google-created account passes through this screen. It is what keeps the 16+
 * legal gate and the CGU acceptance in place on that path.
 */
class GoogleCompleteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private GoogleOAuthService $oauth;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        config(['services.google.enabled' => true]);

        $this->oauth = app(GoogleOAuthService::class);
    }

    private function pendingToken(string $intent = 'face', string $email = 'jean@gmail.com'): string
    {
        return $this->oauth->issuePendingToken([
            'google_id' => 'google-sub-1',
            'email' => $email,
            'intent' => $intent,
            'prenom' => 'Jean',
            'nom' => 'Dupont',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function facePayload(array $overrides = []): array
    {
        return array_merge([
            'pending_token' => $this->pendingToken(),
            'role' => 'face',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '1995-06-15',
            'accept_cgu' => true,
        ], $overrides);
    }

    public function test_it_creates_a_face_without_a_password_and_already_verified(): void
    {
        $response = $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.user.email', 'jean@gmail.com')
            ->assertJsonPath('data.user.userable_type', 'Face')
            ->assertJsonPath('data.user.userable.username', 'jeandupont')
            ->assertJsonPath('data.user.has_password', false)
            ->assertJsonPath('message', 'Inscription réussie');

        $this->assertNotEmpty($response->json('data.token'));

        $user = User::query()->firstOrFail();
        $this->assertNull($user->password);
        $this->assertSame('google-sub-1', $user->google_id);
        $this->assertNotNull($user->google_linked_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->consent_given_at);
        // New consent text on the Face paths ("J'ai 16 ans ou plus et j'accepte…").
        $this->assertSame('2026-08-03', $user->consent_version);
    }

    public function test_a_producer_keeps_the_unchanged_consent_version(): void
    {
        $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $this->pendingToken('producer'),
            'role' => 'producer',
            'type' => 'agency',
            'agency_name' => 'Studio Pro',
            'accept_cgu' => true,
        ])->assertStatus(201);

        $this->assertSame('2026-04-04', User::query()->firstOrFail()->consent_version);
    }

    /**
     * A race on the email (or google_id) between the callback and the completion
     * must surface as a clean 422, never a 500 — and must not be mistaken for a
     * username collision by the registration retry loop.
     */
    public function test_an_email_taken_between_the_exists_check_and_the_insert_is_a_422_not_a_500(): void
    {
        $token = $this->pendingToken();

        // Not a username collision: the retry ladder must not be walked at all.
        $this->partialMock(UsernameGenerator::class)
            ->shouldReceive('generateWithRandomSuffix')
            ->never();

        // Simulate the race: the exists() pre-check passes, then the insert collides.
        User::creating(function (User $user): void {
            $face = Face::factory()->create();
            DB::table('users')->insert([
                'email' => $user->email,
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload([
            'pending_token' => $token,
        ]))->assertStatus(422)->assertJsonPath('error.code', 'EMAIL_ALREADY_USED');
    }

    public function test_a_producer_race_on_the_email_is_a_422_not_a_500(): void
    {
        $token = $this->pendingToken('producer');

        User::creating(function (User $user): void {
            $face = Face::factory()->create();
            DB::table('users')->insert([
                'email' => $user->email,
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $token,
            'role' => 'producer',
            'type' => 'agency',
            'agency_name' => 'Studio Pro',
            'accept_cgu' => true,
        ])->assertStatus(422)->assertJsonPath('error.code', 'EMAIL_ALREADY_USED');
    }

    /**
     * Google already attested the address: mailing a verification link would only
     * add friction, and would block the `verified`-gated routes for nothing.
     */
    public function test_it_sends_no_verification_email(): void
    {
        $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload())->assertStatus(201);

        Notification::assertNotSentTo(User::query()->firstOrFail(), VerifyEmailNotification::class);
    }

    public function test_it_creates_an_agency_producer(): void
    {
        $response = $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $this->pendingToken('producer'),
            'role' => 'producer',
            'type' => 'agency',
            'agency_name' => 'Studio Pro',
            'accept_cgu' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.userable_type', 'Producer')
            ->assertJsonPath('data.user.userable.agency_name', 'Studio Pro');

        $this->assertSame('studio-pro', Producer::query()->firstOrFail()->slug);
    }

    public function test_it_creates_a_particulier_producer_and_splits_the_name(): void
    {
        $response = $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $this->pendingToken('producer'),
            'role' => 'producer',
            'type' => 'particulier',
            'nom_complet' => 'Marie Ange Sossou',
            'accept_cgu' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.userable.first_name', 'Marie Ange')
            ->assertJsonPath('data.user.userable.last_name', 'Sossou');
    }

    public function test_google_completion_lengths_match_the_profile_limits(): void
    {
        $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $this->pendingToken('producer'),
            'role' => 'producer',
            'type' => 'agency',
            'agency_name' => str_repeat('a', 101),
            'accept_cgu' => true,
        ])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['agency_name']]]);

        $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $this->pendingToken('producer'),
            'role' => 'producer',
            'type' => 'particulier',
            'nom_complet' => str_repeat('a', 101),
            'accept_cgu' => true,
        ])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['nom_complet']]]);
    }

    public function test_google_face_names_are_capped_at_the_profile_limit_of_100(): void
    {
        foreach (['nom', 'prenom'] as $field) {
            $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload([$field => str_repeat('a', 101)]))
                ->assertStatus(422)
                ->assertJsonStructure(['error' => ['details' => [$field]]]);
        }

        $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload([
            'nom' => str_repeat('a', 100),
            'prenom' => str_repeat('b', 100),
        ]))->assertStatus(201);
    }

    /**
     * The legacy email-form fields are accepted on the email path only (deploy
     * window): the Google finalisation screen never collected them.
     */
    public function test_deferred_profile_fields_are_not_collected_on_the_google_path(): void
    {
        $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload([
            'username' => 'chosen_by_hand',
            'sexe' => 'homme',
            'nationalite' => 'Béninoise',
            'whatsapp_number' => '+22997000000',
        ]))->assertStatus(201);

        $face = Face::where('nom', 'Dupont')->firstOrFail();

        $this->assertSame('jeandupont', $face->username);
        $this->assertNull($face->sexe);
        $this->assertNull($face->nationalite);
        $this->assertNull($face->whatsapp_number);
    }

    public function test_consent_is_never_skipped(): void
    {
        $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload(['accept_cgu' => false]))
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['accept_cgu']]]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_sixteen_year_gate_still_applies_on_the_google_path(): void
    {
        $payload = $this->facePayload(['date_naissance' => now()->subYears(15)->format('Y-m-d')]);

        $this->postJson('/api/v1/auth/google/complete-registration', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.details.date_naissance.0', 'Vous devez avoir au moins 16 ans pour vous inscrire.');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_pending_token_can_only_be_spent_once(): void
    {
        $token = $this->pendingToken();

        $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $token,
            'role' => 'face',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '1995-06-15',
            'accept_cgu' => true,
        ])->assertStatus(201);

        $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $token,
            'role' => 'face',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '1995-06-15',
            'accept_cgu' => true,
        ])->assertStatus(422)->assertJsonPath('error.code', 'OAUTH_PENDING_INVALID');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_unknown_pending_token_is_refused(): void
    {
        $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload([
            'pending_token' => 'not-a-real-token',
        ]))->assertStatus(422)->assertJsonPath('error.code', 'OAUTH_PENDING_INVALID');
    }

    /**
     * The address can be claimed between the callback and this call.
     */
    public function test_an_address_claimed_in_the_meantime_is_refused(): void
    {
        $token = $this->pendingToken();

        $face = Face::factory()->create();
        User::create([
            'email' => 'jean@gmail.com',
            'password' => Hash::make('Password123'),
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $token,
            'role' => 'face',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '1995-06-15',
            'accept_cgu' => true,
        ])->assertStatus(422)->assertJsonPath('error.code', 'EMAIL_ALREADY_USED');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_honors_the_registration_kill_switch(): void
    {
        config(['app.registration_enabled' => false]);

        $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload())
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'registration_disabled');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_invalid_role_is_refused(): void
    {
        $this->postJson('/api/v1/auth/google/complete-registration', $this->facePayload(['role' => 'admin']))
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['role']]]);
    }
}
