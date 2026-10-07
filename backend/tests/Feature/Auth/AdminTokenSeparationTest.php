<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

/**
 * Admins and users share numeric ids : an Admin bearer token must never be
 * accepted on a user surface (REST groups, notifications, broadcasting).
 */
class AdminTokenSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(Admin|User $principal): array
    {
        return ['Authorization' => 'Bearer '.$principal->createToken('t', ['2fa'])->plainTextToken];
    }

    public function test_admin_token_is_refused_on_user_notification_routes(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create(['id' => $user->id]);

        $this->getJson('/api/v1/me/notifications', $this->bearer($admin))->assertStatus(403);
        $this->getJson('/api/v1/me/notifications/unread-count', $this->bearer($admin))->assertStatus(403);
        $this->postJson('/api/v1/me/notifications/read-all', [], $this->bearer($admin))->assertStatus(403);
    }

    public function test_user_token_still_works_on_user_notification_routes(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/me/notifications', $this->bearer($user))->assertOk();
    }

    public function test_admin_token_is_refused_on_the_generic_user_group(): void
    {
        $admin = Admin::factory()->create();

        $this->getJson('/api/v1/user', $this->bearer($admin))->assertStatus(403);
        $this->getJson('/api/v1/email/verification-status', $this->bearer($admin))->assertStatus(403);
        $this->getJson('/api/v1/face/profile', $this->bearer($admin))->assertStatus(403);
        $this->getJson('/api/v1/producer/profile', $this->bearer($admin))->assertStatus(403);
    }

    public function test_user_token_is_still_refused_on_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/admin/me', $this->bearer($user))->assertStatus(403);
    }

    public function test_admin_token_still_works_on_admin_routes(): void
    {
        $admin = Admin::factory()->create();

        $this->getJson('/api/v1/admin/me', $this->bearer($admin))->assertOk();
    }

    private function useRealBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => 'localhost',
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
    }

    private function authChannel(string $channel, array $headers)
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-'.$channel,
        ], $headers);
    }

    public function test_admin_token_cannot_subscribe_to_a_user_private_channel(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create(['id' => $user->id]);
        $this->useRealBroadcaster();

        $this->authChannel('App.Models.User.'.$user->id, $this->bearer($admin))->assertStatus(403);
    }

    public function test_user_token_can_subscribe_to_its_own_private_channel_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->useRealBroadcaster();

        $this->authChannel('App.Models.User.'.$user->id, $this->bearer($user))->assertOk();
        $this->authChannel('App.Models.User.'.$other->id, $this->bearer($user))->assertStatus(403);
    }

    public function test_admin_token_cannot_subscribe_to_a_booking_channel(): void
    {
        $booking = Booking::factory()->create();
        $admin = Admin::factory()->create(['id' => $booking->producer_id]);
        $this->useRealBroadcaster();

        $this->authChannel('booking.'.$booking->id, $this->bearer($admin))->assertStatus(403);
    }

    public function test_booking_party_can_subscribe_to_the_booking_channel(): void
    {
        $booking = Booking::factory()->create();
        $producerUser = User::findOrFail($booking->producer_id);
        $this->useRealBroadcaster();

        $this->authChannel('booking.'.$booking->id, $this->bearer($producerUser))->assertOk();
    }
}
