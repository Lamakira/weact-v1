<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTokenLifetimeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private const PASSWORD = 'SecurePass123';

    public function test_enrolment_login_token_expires_in_12_hours(): void
    {
        Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com', 'password' => self::PASSWORD]);

        $token = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
            'password' => self::PASSWORD,
        ])->assertOk()->json('data.token');

        $this->assertExpiresInTwelveHours($token);
    }

    public function test_two_factor_login_token_expires_in_12_hours(): void
    {
        Admin::factory()->create([
            'email' => 'admin@test.com',
            'password' => self::PASSWORD,
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaaa-bbbbb'],
        ]);

        $challenge = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
            'password' => self::PASSWORD,
        ])->json('data.challenge');

        $google2fa = new Google2FA;
        $token = $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $google2fa->oathTotp(self::SECRET, $google2fa->getTimestamp()),
        ])->assertOk()->json('data.token');

        $this->assertExpiresInTwelveHours($token);
    }

    public function test_admin_token_is_accepted_before_12_hours(): void
    {
        $token = Admin::factory()->create()
            ->createToken('admin-token', [Admin::ABILITY_TWO_FACTOR], now()->addHours(Admin::TOKEN_TTL_HOURS))
            ->plainTextToken;

        $this->travel(11)->hours();

        $this->withToken($token)->getJson('/api/v1/admin/me')->assertOk();
    }

    public function test_admin_token_is_rejected_after_12_hours(): void
    {
        $token = Admin::factory()->create()
            ->createToken('admin-token', [Admin::ABILITY_TWO_FACTOR], now()->addHours(Admin::TOKEN_TTL_HOURS))
            ->plainTextToken;

        // Pas d'appel authentifié avant le voyage (cf. LoginTest::test_token_expires_after_30_days).
        $this->travel(13)->hours();

        $this->withToken($token)->getJson('/api/v1/admin/me')->assertStatus(401);
    }

    public function test_user_token_is_still_valid_after_12_hours(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->assertNull(PersonalAccessToken::findToken($token)->expires_at);

        $this->travel(13)->hours();

        $this->withToken($token)->getJson('/api/v1/user')->assertOk();
    }

    private function assertExpiresInTwelveHours(string $plainTextToken): void
    {
        $expiresAt = PersonalAccessToken::findToken($plainTextToken)->expires_at;

        $this->assertNotNull($expiresAt);
        $this->assertEqualsWithDelta(
            now()->addHours(12)->getTimestamp(),
            $expiresAt->getTimestamp(),
            5,
        );
    }
}
