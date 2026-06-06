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

        User::create([
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

        User::create([
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

    public function test_throttle_distinguishes_different_emails_on_same_ip(): void
    {
        // Per-account limiter is keyed by email|ip: from a single IP, two
        // different emails keep independent counters (one throttled, the other not).
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

    public function test_throttle_does_not_lock_victim_from_a_different_ip(): void
    {
        $attackerIp = ['REMOTE_ADDR' => '203.0.113.10'];

        // Attacker spams the victim's email from their own IP until throttled.
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables($attackerIp)->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        $this->assertFrenchThrottleResponse(
            $this->withServerVariables($attackerIp)->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => 'wrongpassword',
            ])
        );

        // The real victim, from a DIFFERENT IP and with the correct password,
        // is NOT locked out — the lockout is scoped to the attacker's IP.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'face@example.com',
                'password' => $this->password,
            ])->assertStatus(200);
    }

    public function test_ip_throttle_bounds_spraying_across_many_emails(): void
    {
        $sprayerIp = ['REMOTE_ADDR' => '203.0.113.99'];

        // One attempt per distinct email never trips the per-account limiter,
        // but the coarse per-IP route throttle (30/min) bounds the spray.
        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables($sprayerIp)->postJson('/api/v1/auth/login', [
                'email' => "spray{$i}@example.com",
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        $this->withServerVariables($sprayerIp)
            ->postJson('/api/v1/auth/login', [
                'email' => 'spray-final@example.com',
                'password' => 'wrongpassword',
            ])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'THROTTLED');
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

    public function test_token_expires_after_30_days(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => $this->password,
        ])->json('data.token');

        // Past the 30-day lifetime (config/sanctum.php expiration) the token is rejected.
        // NB: we deliberately do NOT make an authenticated call before travelling — that would
        // cache the user on the persistent test-container 'web' guard (via api.token's
        // Auth::setUser) and mask the expiry. The "works before expiry" path is covered by
        // test_token_can_be_used_for_authenticated_requests.
        $this->travel(31)->days();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user')->assertStatus(401);
    }

    public function test_expired_token_is_rejected_even_when_stale_session_exists(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'face@example.com',
            'password' => $this->password,
        ])->json('data.token');

        $staleSessionUser = User::where('email', 'producer@example.com')->firstOrFail();

        $this->travel(31)->days();

        $this->actingAs($staleSessionUser)
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user')
            ->assertStatus(401);
    }
}
