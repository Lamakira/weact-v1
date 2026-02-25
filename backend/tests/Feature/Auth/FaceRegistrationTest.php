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
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'sexe' => 'homme',
            'date_naissance' => '1995-06-15',
            'nationalite' => 'Béninoise',
            'pays' => 'Bénin',
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
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.details.email.0', 'Cet email est déjà utilisé');
    }

    public function test_duplicate_username_returns_422_with_error(): void
    {
        // Create existing face with the same username
        Face::create([
            'nom' => 'Existing',
            'prenom' => 'User',
            'username' => 'johndoe',
        ]);

        $response = $this->postJson('/api/v1/auth/register/face', $this->validData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'username',
                    ],
                ],
            ])
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.details.username.0', 'Ce nom d\'utilisateur est déjà pris');
    }

    public function test_weak_password_returns_422_with_requirements(): void
    {
        // Test password too short
        $data = $this->validData;
        $data['password'] = 'Short1';
        $data['password_confirmation'] = 'Short1';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins 8 caractères');

        // Test password without uppercase
        $data['password'] = 'password123';
        $data['password_confirmation'] = 'password123';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins une majuscule et un chiffre');

        // Test password without number
        $data['password'] = 'PasswordABC';
        $data['password_confirmation'] = 'PasswordABC';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins une majuscule et un chiffre');
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
                        'username',
                        'email',
                        'password',
                        'sexe',
                        'date_naissance',
                        'nationalite',
                        'pays',
                    ],
                ],
            ])
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.details.nom.0', 'Le nom est obligatoire')
            ->assertJsonPath('error.details.prenom.0', 'Le prénom est obligatoire')
            ->assertJsonPath('error.details.username.0', 'Le nom d\'utilisateur est obligatoire')
            ->assertJsonPath('error.details.email.0', 'L\'email est obligatoire')
            ->assertJsonPath('error.details.password.0', 'Le mot de passe est obligatoire')
            ->assertJsonPath('error.details.sexe.0', 'Le sexe est obligatoire.')
            ->assertJsonPath('error.details.date_naissance.0', 'La date de naissance est obligatoire.')
            ->assertJsonPath('error.details.nationalite.0', 'La nationalité est obligatoire.')
            ->assertJsonPath('error.details.pays.0', 'Le pays est obligatoire.');
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
            'sexe' => 'homme',
            'date_naissance' => '1995-06-15',
            'nationalite' => 'Béninoise',
            'pays' => 'Bénin',
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

    public function test_password_confirmation_must_match(): void
    {
        $data = $this->validData;
        $data['password_confirmation'] = 'DifferentPassword123';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'La confirmation du mot de passe ne correspond pas');
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

    public function test_invalid_sexe_returns_422(): void
    {
        $data = $this->validData;
        $data['sexe'] = 'invalide';

        $response = $this->postJson('/api/v1/auth/register/face', $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.sexe.0', 'Le sexe sélectionné est invalide.');
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
