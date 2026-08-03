<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Face;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FaceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private array $validData;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->validData = [
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john@example.com',
            'date_naissance' => '1995-06-15',
            'password' => 'Password123',
            'accept_cgu' => true,
        ];
    }

    public function test_successful_face_registration_returns_201_with_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'userable_type',
                        'userable' => [
                            'id',
                            'nom',
                            'prenom',
                            'username',
                        ],
                    ],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonPath('data.user.userable_type', 'Face')
            ->assertJsonPath('data.user.userable.nom', 'Doe')
            ->assertJsonPath('data.user.userable.prenom', 'John')
            ->assertJsonPath('data.user.userable.username', 'johndoe')
            ->assertJsonPath('message', 'Inscription réussie');

        // Verify token is present and not empty
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_registration_succeeds_with_only_the_six_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

        $response->assertStatus(201);

        $this->assertSame(
            ['nom', 'prenom', 'email', 'date_naissance', 'password', 'accept_cgu'],
            array_keys($this->validData),
        );
    }

    public function test_username_is_generated_from_prenom_and_nom(): void
    {
        $data = [
            ...$this->validData,
            'prenom' => 'Léa',
            'nom' => "Gbèdo N'Djamena",
        ];

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.userable.username', 'leagbedondjamena');
    }

    public function test_username_generation_disambiguates_homonyms(): void
    {
        Face::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'username' => 'johndoe',
        ]);

        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.userable.username', 'johndoe2');
    }

    public function test_deferred_profile_fields_are_not_collected_at_registration(): void
    {
        // Sending them anyway must not persist them: they are no longer validated,
        // so they must never reach Face::create through mass assignment.
        $data = [
            ...$this->validData,
            'username' => 'chosen_by_hand',
            'sexe' => 'homme',
            'nationalite' => 'Béninoise',
            'whatsapp_number' => '+22997000000',
        ];

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(201);

        $face = Face::where('nom', 'Doe')->firstOrFail();

        $this->assertSame('johndoe', $face->username);
        $this->assertNull($face->sexe);
        $this->assertNull($face->nationalite);
        $this->assertNull($face->whatsapp_number);

        $this->assertDatabaseMissing('faces', ['username' => 'chosen_by_hand']);
    }

    public function test_pays_falls_back_to_its_database_default(): void
    {
        $this->postJson('/api/v1/auth/register/face', $this->validData)->assertStatus(201);

        $this->assertSame('Bénin', Face::where('nom', 'Doe')->firstOrFail()->pays);
    }

    public function test_registration_status_endpoint_returns_enabled(): void
    {
        $response = $this->getJson('/api/v1/auth/registration-status');

        $response->assertOk()
            ->assertJsonPath('data.enabled', true);
    }

    public function test_registration_status_endpoint_returns_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $response = $this->getJson('/api/v1/auth/registration-status');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_registration_status_endpoint_emits_cache_control_header_300s(): void
    {
        $response = $this->getJson('/api/v1/auth/registration-status');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, private');
    }

    public function test_registration_returns_403_when_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'registration_disabled');

        $this->assertDatabaseCount('faces', 0);
    }

    public function test_registration_returns_403_with_empty_body_when_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $response = $this->postJson('/api/v1/auth/register/face', []);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'registration_disabled');
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
            'email' => 'john@example.com',
            'password' => 'password',
            'userable_type' => Face::class,
            'userable_id' => $existingFace->id,
        ]);

        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

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
        $data = $this->validData;
        $data['password'] = 'Short1';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins 8 caractères');

        // Test password without uppercase
        $data['password'] = 'password123';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins une majuscule et un chiffre');

        // Test password without number
        $data['password'] = 'PasswordABC';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins une majuscule et un chiffre');
    }

    public function test_password_confirmation_is_no_longer_required(): void
    {
        $data = $this->validData;
        $data['password_confirmation'] = 'SomethingElse456';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertTrue(password_verify('Password123', $user->password));
    }

    public function test_missing_fields_return_422_with_field_errors(): void
    {
        $response = $this->postJson('/api/v1/auth/register/face', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'nom',
                        'prenom',
                        'email',
                        'date_naissance',
                        'password',
                        'accept_cgu',
                    ],
                ],
            ])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.nom.0', 'Le nom est obligatoire')
            ->assertJsonPath('error.details.prenom.0', 'Le prénom est obligatoire')
            ->assertJsonPath('error.details.email.0', 'L\'email est obligatoire')
            ->assertJsonPath('error.details.date_naissance.0', 'La date de naissance est obligatoire.')
            ->assertJsonPath('error.details.password.0', 'Le mot de passe est obligatoire');

        // Removed fields must no longer be reported as missing
        $response->assertJsonMissingPath('error.details.username')
            ->assertJsonMissingPath('error.details.sexe')
            ->assertJsonMissingPath('error.details.nationalite')
            ->assertJsonMissingPath('error.details.pays');
    }

    public function test_accept_cgu_must_be_accepted(): void
    {
        $data = $this->validData;
        $data['accept_cgu'] = false;

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath(
                'error.details.accept_cgu.0',
                'Vous devez avoir 16 ans ou plus et accepter les CGU et la Politique de Confidentialité.'
            );
    }

    public function test_face_record_is_linked_to_user_via_polymorphic(): void
    {
        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

        $response->assertStatus(201);

        // Verify database records
        $this->assertDatabaseHas('faces', [
            'nom' => 'Doe',
            'prenom' => 'John',
            'username' => 'johndoe',
            'date_naissance' => '1995-06-15',
        ]);

        $face = Face::where('username', 'johndoe')->first();

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        // Verify polymorphic relationship works
        $user = User::where('email', 'john@example.com')->first();
        $this->assertInstanceOf(Face::class, $user->userable);
        $this->assertEquals('johndoe', $user->userable->username);

        // Verify reverse relationship
        $this->assertInstanceOf(User::class, $face->user);
        $this->assertEquals('john@example.com', $face->user->email);
    }

    public function test_password_is_hashed_on_registration(): void
    {
        $this->postJson('/api/v1/auth/register/face', $this->validData);

        $user = User::where('email', 'john@example.com')->first();

        // Password should be hashed, not plain text
        $this->assertNotEquals('Password123', $user->password);
        $this->assertTrue(password_verify('Password123', $user->password));
    }

    public function test_sends_verification_email_on_successful_registration(): void
    {
        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_new_user_has_unverified_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();

        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_future_date_naissance_returns_422(): void
    {
        $data = $this->validData;
        $data['date_naissance'] = now()->addDay()->format('Y-m-d');

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['date_naissance']]]);
    }

    public function test_underage_date_naissance_returns_422(): void
    {
        $data = $this->validData;
        $data['date_naissance'] = now()->subYears(15)->format('Y-m-d');

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.date_naissance.0', 'Vous devez avoir au moins 16 ans pour vous inscrire.');
    }

    public function test_exactly_sixteen_years_old_is_accepted(): void
    {
        $data = $this->validData;
        $data['date_naissance'] = now()->subYears(16)->format('Y-m-d');

        $this->postJson('/api/v1/auth/register/face', $data)->assertStatus(201);
    }

    public function test_age_accessor_calculates_correctly(): void
    {
        $face = Face::create([
            'nom' => 'Test',
            'prenom' => 'Face',
            'username' => 'testface',
            'sexe' => 'homme',
            'date_naissance' => '1995-06-15',
            'nationalite' => 'Béninoise',
            'pays' => 'Bénin',
        ]);

        $this->assertEquals(\Carbon\Carbon::parse('1995-06-15')->age, $face->age);
    }

    public function test_age_accessor_returns_null_when_no_date_naissance(): void
    {
        $face = Face::create([
            'nom' => 'Test',
            'prenom' => 'Face',
            'username' => 'testface2',
        ]);

        $this->assertNull($face->age);
    }
}
