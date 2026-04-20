<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProducerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private array $validAgencyData;

    private array $validParticulierData;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->validAgencyData = [
            'type' => 'agency',
            'agency_name' => 'Studio Pro',
            'email' => 'agency@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'accept_cgu' => true,
        ];

        $this->validParticulierData = [
            'type' => 'particulier',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'accept_cgu' => true,
        ];
    }

    public function test_registration_returns_403_when_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $response = $this->postJson('/api/v1/auth/register/producer', $this->validAgencyData);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'registration_disabled');
    }

    public function test_registration_returns_403_with_empty_body_when_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $response = $this->postJson('/api/v1/auth/register/producer', []);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'registration_disabled');
    }

    public function test_successful_agency_registration_returns_201_with_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register/producer', $this->validAgencyData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'userable_type',
                        'userable' => [
                            'id',
                            'type',
                            'agency_name',
                            'display_name',
                        ],
                    ],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.email', 'agency@example.com')
            ->assertJsonPath('data.user.userable_type', 'Producer')
            ->assertJsonPath('data.user.userable.type', 'agency')
            ->assertJsonPath('data.user.userable.agency_name', 'Studio Pro')
            ->assertJsonPath('data.user.userable.display_name', 'Studio Pro')
            ->assertJsonPath('message', 'Inscription réussie');

        // Verify token is present and not empty
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_successful_particulier_registration_returns_201_with_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register/producer', $this->validParticulierData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'userable_type',
                        'userable' => [
                            'id',
                            'type',
                            'first_name',
                            'last_name',
                            'display_name',
                        ],
                    ],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.email', 'jean@example.com')
            ->assertJsonPath('data.user.userable_type', 'Producer')
            ->assertJsonPath('data.user.userable.type', 'particulier')
            ->assertJsonPath('data.user.userable.first_name', 'Jean')
            ->assertJsonPath('data.user.userable.last_name', 'Dupont')
            ->assertJsonPath('data.user.userable.display_name', 'Jean Dupont')
            ->assertJsonPath('message', 'Inscription réussie');

        // Verify token is present and not empty
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_duplicate_email_returns_422_with_error(): void
    {
        // Create existing user with the same email
        $existingFace = Face::create([
            'nom' => 'Existing',
            'prenom' => 'User',
            'username' => 'existinguser',
        ]);

        User::create([
            'email' => 'agency@example.com',
            'password' => 'password',
            'userable_type' => Face::class,
            'userable_id' => $existingFace->id,
        ]);

        $response = $this->postJson('/api/v1/auth/register/producer', $this->validAgencyData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'email',
                    ],
                ],
            ])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.email.0', 'Cet email est déjà utilisé');
    }

    public function test_weak_password_returns_422_with_requirements(): void
    {
        // Test password too short
        $data = $this->validAgencyData;
        $data['password'] = 'Short1';
        $data['password_confirmation'] = 'Short1';

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins 8 caractères');

        // Test password without uppercase
        $data['password'] = 'password123';
        $data['password_confirmation'] = 'password123';

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins une majuscule et un chiffre');

        // Test password without number
        $data['password'] = 'PasswordABC';
        $data['password_confirmation'] = 'PasswordABC';

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins une majuscule et un chiffre');
    }

    public function test_agency_without_agency_name_returns_422(): void
    {
        $data = $this->validAgencyData;
        unset($data['agency_name']);

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'agency_name',
                    ],
                ],
            ])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.agency_name.0', 'Le nom de l\'agence est obligatoire');
    }

    public function test_particulier_without_first_name_returns_422(): void
    {
        $data = $this->validParticulierData;
        unset($data['first_name']);

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'first_name',
                    ],
                ],
            ])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.first_name.0', 'Le prénom est obligatoire');
    }

    public function test_particulier_without_last_name_returns_422(): void
    {
        $data = $this->validParticulierData;
        unset($data['last_name']);

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'last_name',
                    ],
                ],
            ])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.last_name.0', 'Le nom est obligatoire');
    }

    public function test_producer_record_is_linked_to_user_via_polymorphic(): void
    {
        $response = $this->postJson('/api/v1/auth/register/producer', $this->validAgencyData);

        $response->assertStatus(201);

        // Verify database records
        $this->assertDatabaseHas('producers', [
            'type' => 'agency',
            'agency_name' => 'Studio Pro',
        ]);

        $producer = Producer::where('agency_name', 'Studio Pro')->first();

        $this->assertDatabaseHas('users', [
            'email' => 'agency@example.com',
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        // Verify polymorphic relationship works
        $user = User::where('email', 'agency@example.com')->first();
        $this->assertInstanceOf(Producer::class, $user->userable);
        $this->assertEquals('Studio Pro', $user->userable->agency_name);
        $this->assertEquals('agency', $user->userable->type->value);

        // Verify reverse relationship
        $this->assertInstanceOf(User::class, $producer->user);
        $this->assertEquals('agency@example.com', $producer->user->email);
    }

    public function test_password_is_hashed_on_registration(): void
    {
        $this->postJson('/api/v1/auth/register/producer', $this->validAgencyData);

        $user = User::where('email', 'agency@example.com')->first();

        // Password should be hashed, not plain text
        $this->assertNotEquals('Password123', $user->password);
        $this->assertTrue(password_verify('Password123', $user->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $data = $this->validAgencyData;
        $data['password_confirmation'] = 'DifferentPassword123';

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'La confirmation du mot de passe ne correspond pas');
    }

    public function test_invalid_type_returns_422(): void
    {
        $data = $this->validAgencyData;
        $data['type'] = 'invalid_type';

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.type.0', 'Type de compte invalide');
    }

    public function test_missing_type_returns_422(): void
    {
        $data = $this->validAgencyData;
        unset($data['type']);

        $response = $this->postJson('/api/v1/auth/register/producer', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.type.0', 'Veuillez choisir un type de compte');
    }

    public function test_sends_verification_email_on_successful_registration(): void
    {
        $response = $this->postJson('/api/v1/auth/register/producer', $this->validAgencyData);

        $response->assertStatus(201);

        $user = User::where('email', 'agency@example.com')->first();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_new_producer_has_unverified_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register/producer', $this->validAgencyData);

        $response->assertStatus(201);

        $user = User::where('email', 'agency@example.com')->first();

        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }
}
