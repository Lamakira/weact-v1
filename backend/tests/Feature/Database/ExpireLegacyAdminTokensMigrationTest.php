<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpireLegacyAdminTokensMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        (require database_path('migrations/2026_10_16_000001_expire_legacy_admin_tokens.php'))->up();
    }

    public function test_legacy_admin_tokens_get_created_at_plus_12_hours(): void
    {
        $admin = Admin::factory()->create();
        $old = $admin->createToken('admin-token', ['2fa']);
        $recent = $admin->createToken('admin-token', ['2fa']);

        DB::table('personal_access_tokens')->where('id', $old->accessToken->id)
            ->update(['created_at' => now()->subDays(3), 'expires_at' => null]);
        DB::table('personal_access_tokens')->where('id', $recent->accessToken->id)
            ->update(['created_at' => now()->subHour(), 'expires_at' => null]);

        $this->runMigration();

        $oldRow = DB::table('personal_access_tokens')->find($old->accessToken->id);
        $recentRow = DB::table('personal_access_tokens')->find($recent->accessToken->id);

        $this->assertTrue(now()->gt($oldRow->expires_at));
        $this->assertEqualsWithDelta(
            now()->subHour()->addHours(12)->getTimestamp(),
            strtotime($recentRow->expires_at),
            5,
        );
    }

    public function test_user_tokens_and_already_expiring_admin_tokens_are_untouched(): void
    {
        $user = User::factory()->create();
        $userToken = $user->createToken('auth-token');
        $admin = Admin::factory()->create();
        $expiry = now()->addHours(5)->startOfSecond();
        $adminToken = $admin->createToken('admin-token', ['2fa'], $expiry);

        $this->runMigration();

        $this->assertNull(DB::table('personal_access_tokens')->find($userToken->accessToken->id)->expires_at);
        $this->assertSame(
            $expiry->getTimestamp(),
            strtotime(DB::table('personal_access_tokens')->find($adminToken->accessToken->id)->expires_at),
        );
    }
}
