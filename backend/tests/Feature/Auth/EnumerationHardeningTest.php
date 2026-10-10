<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EnumerationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_forgot_password_returns_same_response_for_existing_throttled_and_unknown_email(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@test.com']);

        $first = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@test.com']);
        // 2nd request on the same account hits the broker's own RESET_THROTTLED.
        $second = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@test.com']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@test.com']);

        $first->assertOk();
        $second->assertOk();
        $unknown->assertOk();
        $this->assertSame($first->json(), $second->json());
        $this->assertSame($first->json(), $unknown->json());
    }

    public function test_admin_forgot_password_returns_same_response_for_existing_throttled_and_unknown_email(): void
    {
        Notification::fake();
        Admin::factory()->create(['email' => 'known@test.com']);

        $first = $this->postJson('/api/v1/admin/forgot-password', ['email' => 'known@test.com']);
        $second = $this->postJson('/api/v1/admin/forgot-password', ['email' => 'known@test.com']);
        $unknown = $this->postJson('/api/v1/admin/forgot-password', ['email' => 'unknown@test.com']);

        $first->assertOk();
        $second->assertOk();
        $unknown->assertOk();
        $this->assertSame($first->json(), $second->json());
        $this->assertSame($first->json(), $unknown->json());
    }

    private function pathAndQuery(string $url): string
    {
        return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    public function test_verification_link_errors_are_identical_for_unknown_user_bad_hash_and_bad_signature(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $unknownUser = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => 999999, 'hash' => sha1('x@test.com'),
        ]);
        $badHash = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => 'bad-hash',
        ]);
        $badSignature = "/api/v1/auth/email/verify/{$user->id}/".sha1($user->email).'?expires=9999999999&signature=forged';
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);

        $responses = [
            $this->getJson($this->pathAndQuery($unknownUser)),
            $this->getJson($this->pathAndQuery($badHash)),
            $this->getJson($badSignature),
            $this->getJson($this->pathAndQuery($expired)),
        ];

        foreach ($responses as $response) {
            $response->assertStatus(403)->assertJsonPath('error.code', 'INVALID_VERIFICATION_LINK');
            $this->assertSame($responses[0]->json(), $response->json());
        }
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_change_confirm_errors_are_identical_for_unknown_user_bad_hash_and_bad_signature(): void
    {
        $user = User::factory()->create(['pending_email' => 'new@test.com']);

        $unknownUser = URL::temporarySignedRoute('email-change.confirm', now()->addHour(), [
            'id' => 999999, 'hash' => sha1('new@test.com'),
        ]);
        $badHash = URL::temporarySignedRoute('email-change.confirm', now()->addHour(), [
            'id' => $user->id, 'hash' => 'bad-hash',
        ]);
        $badSignature = "/api/v1/auth/email/change/confirm/{$user->id}/".sha1('new@test.com').'?expires=9999999999&signature=forged';

        $responses = [
            $this->getJson($this->pathAndQuery($unknownUser)),
            $this->getJson($this->pathAndQuery($badHash)),
            $this->getJson($badSignature),
        ];

        foreach ($responses as $response) {
            $response->assertStatus(403)->assertJsonPath('error.code', 'INVALID_CONFIRMATION_LINK');
            $this->assertSame($responses[0]->json(), $response->json());
        }
    }

    public function test_verification_and_change_confirm_routes_are_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/auth/email/verify/1/abc?expires=1&signature=x')->assertStatus(403);
        }
        $this->getJson('/api/v1/auth/email/verify/1/abc?expires=1&signature=x')->assertStatus(429);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/auth/email/change/confirm/1/abc?expires=1&signature=x')->assertStatus(403);
        }
        $this->getJson('/api/v1/auth/email/change/confirm/1/abc?expires=1&signature=x')->assertStatus(429);
    }
}
