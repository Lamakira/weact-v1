<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private string $password = 'Password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $face = Face::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'username' => 'johndoe',
        ]);

        $this->faceUser = User::create([
            'email' => 'face@example.com',
            'password' => Hash::make($this->password),
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        // Create a Producer user
        $producer = Producer::create([
            'type' => 'agency',
            'agency_name' => 'Test Agency',
        ]);

        $this->producerUser = User::create([
            'email' => 'producer@example.com',
            'password' => Hash::make($this->password),
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    private function assertFrenchThrottleResponse(TestResponse $response): void
    {
        $response->assertStatus(429)
            ->assertExactJson([
                'error' => [
                    'message' => 'Trop de tentatives de connexion. Veuillez réessayer dans une minute.',
                    'code' => 'THROTTLED',
                ],
            ]);
    }

    public function test_successful_face_login_returns_200_with_token_and_user_data(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => $this->password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'userable_type',
                        'userable',
                    ],
                    'token',
                ],
                'message',
                'meta',
            ])
            ->assertJsonPath('data.user.email', 'face@example.com')
            ->assertJsonPath('data.user.userable_type', 'Face')
            ->assertJsonPath('message', 'Connexion réussie');

        // Verify token is present and not empty
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_successful_producer_login_returns_200_with_token_and_user_data(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'producer@example.com',
            'password' => $this->password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'userable_type',
                        'userable',
                    ],
                    'token',
                ],
                'message',
                'meta',
            ])
            ->assertJsonPath('data.user.email', 'producer@example.com')
            ->assertJsonPath('data.user.userable_type', 'Producer')
            ->assertJsonPath('message', 'Connexion réussie');

        // Verify token is present and not empty
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_invalid_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => $this->password,
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure([
                'error' => [
                    'message',
                    'code',
                ],
            ])
            ->assertJsonPath('error.message', 'Email ou mot de passe incorrect')
            ->assertJsonPath('error.code', 'AUTH_FAILED');
    }

    public function test_invalid_password_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure([
                'error' => [
                    'message',
                    'code',
                ],
            ])
            ->assertJsonPath('error.message', 'Email ou mot de passe incorrect')
            ->assertJsonPath('error.code', 'AUTH_FAILED');
    }

    public function test_non_existent_user_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.message', 'Email ou mot de passe incorrect')
            ->assertJsonPath('error.code', 'AUTH_FAILED');
    }

    public function test_missing_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => $this->password,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'email',
                    ],
                ],
            ]);
    }

    public function test_missing_password_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'password',
                    ],
                ],
            ]);
    }

    public function test_response_includes_user_role_userable_type(): void
    {
        // Test Face user
        $faceResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => $this->password,
        ]);

        $faceResponse->assertStatus(200)
            ->assertJsonPath('data.user.userable_type', 'Face');

        // Test Producer user
        $producerResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'producer@example.com',
            'password' => $this->password,
        ]);

        $producerResponse->assertStatus(200)
            ->assertJsonPath('data.user.userable_type', 'Producer');
    }

    public function test_rate_limiting_after_5_attempts_returns_429(): void
    {
        // Make 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertFrenchThrottleResponse($response);
    }

    public function test_sixth_failed_login_returns_french_429_in_error_envelope_format(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => 'wrongpassword',
            ]);

            $response->assertStatus(401);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertFrenchThrottleResponse($response);
    }

    public function test_successful_login_resets_throttle_counter(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => $this->password,
        ])->assertStatus(200);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    public function test_validation_422_does_not_consume_throttle_budget(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'password' => 'wrongpassword',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => $this->password,
        ])->assertStatus(200);
    }

    public function test_throttle_is_keyed_by_email_not_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'producer@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'producer@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertFrenchThrottleResponse($response);
    }

    public function test_throttle_expires_after_60_seconds_via_time_travel(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        $this->assertFrenchThrottleResponse($this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => 'wrongpassword',
        ]));

        $this->travel(61)->seconds();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    public function test_account_deactivated_does_not_hit_or_clear_throttle(): void
    {
        $deactivatedFace = Face::create([
            'nom' => 'Disabled',
            'prenom' => 'User',
            'username' => 'disableduser',
        ]);

        User::create([
            'email' => 'deactivated@example.com',
            'password' => Hash::make($this->password),
            'is_active' => false,
            'userable_type' => Face::class,
            'userable_id' => $deactivatedFace->id,
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'deactivated@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'deactivated@example.com',
            'password' => $this->password,
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_DEACTIVATED');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'deactivated@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);

        $this->assertFrenchThrottleResponse($this->postJson('/api/v1/auth/login', [
            'email' => 'deactivated@example.com',
            'password' => 'wrongpassword',
        ]));
    }

    public function test_login_returns_userable_data(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => $this->password,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.userable.nom', 'Doe')
            ->assertJsonPath('data.user.userable.prenom', 'John')
            ->assertJsonPath('data.user.userable.username', 'johndoe');
    }

    public function test_token_can_be_used_for_authenticated_requests(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => $this->password,
        ]);

        $token = $loginResponse->json('data.token');

        // Use token for authenticated request
        $userResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user');

        $userResponse->assertStatus(200);
    }
}
