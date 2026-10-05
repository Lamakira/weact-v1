<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionReRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_re_registration_after_account_deletion_uses_new_bearer_token_even_if_old_session_exists(): void
    {
        $firstRegistration = $this->postJson('/api/v1/auth/register/face', [
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john@example.com',
            'date_naissance' => '1995-06-15',
            'password' => 'Password123',
            'accept_cgu' => true,
        ]);

        $firstRegistration->assertCreated();

        $firstToken = $firstRegistration->json('data.token');
        $deletedUserId = (int) $firstRegistration->json('data.user.id');

        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->deleteJson('/api/v1/user/account', [
                'password' => 'Password123',
            ])
            ->assertOk();

        $deletedUser = User::findOrFail($deletedUserId);

        $this->assertFalse($deletedUser->is_active);
        $this->assertSame("deleted_{$deletedUserId}@anonymized.weact.bj", $deletedUser->email);

        $secondRegistration = $this->postJson('/api/v1/auth/register/face', [
            'nom' => 'Martin',
            'prenom' => 'Alice',
            'email' => 'john@example.com',
            'date_naissance' => '1998-04-10',
            'password' => 'Password123',
            'accept_cgu' => true,
        ]);

        $secondRegistration->assertCreated()
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonPath('data.user.userable.nom', 'Martin')
            ->assertJsonPath('data.user.userable.prenom', 'Alice')
            ->assertJsonPath('data.user.userable.username', 'alicemartin');

        $secondToken = $secondRegistration->json('data.token');
        $newUserId = (int) $secondRegistration->json('data.user.id');
        $newUserableUuid = $secondRegistration->json('data.user.userable.id');

        // Simulate a stale stateful session still pointing to the deleted account.
        $userResponse = $this->actingAs($deletedUser)
            ->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/v1/user');

        $userResponse->assertOk()
            ->assertJsonPath('data.id', $newUserId)
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.userable_type', 'Face');

        // Verify userable.id matches the UUID from registration
        $this->assertEquals($newUserableUuid, $userResponse->json('data.userable.id'));

        $basicInfoResponse = $this->actingAs($deletedUser)
            ->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/v1/face/basic-info');

        $basicInfoResponse->assertOk()
            ->assertJsonPath('data.nom', 'Martin')
            ->assertJsonPath('data.prenom', 'Alice')
            ->assertJsonPath('data.username', 'alicemartin');
    }

    /**
     * Deletion rewrites the email, so the email-matching branch correctly misses a
     * deleted row. But google_id is matched FIRST: without unlinking it on
     * deletion, signing back in with the same Google account would land straight
     * on the anonymized, deactivated row.
     */
    public function test_deletion_unlinks_the_google_identity_so_re_registration_starts_fresh(): void
    {
        config(['services.google.enabled' => true]);

        $oauth = app(GoogleOAuthService::class);

        $first = $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $oauth->issuePendingToken([
                'google_id' => 'google-sub-1',
                'email' => 'jean@gmail.com',
                'intent' => 'face',
                'prenom' => 'Jean',
                'nom' => 'Dupont',
            ]),
            'role' => 'face',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '1995-06-15',
            'accept_cgu' => true,
        ]);

        $first->assertCreated();
        $firstUserId = (int) $first->json('data.user.id');
        $firstToken = $first->json('data.token');

        // An OAuth-only account has no password: erasure is confirmed with a fresh
        // Google re-authentication ticket instead.
        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->deleteJson('/api/v1/user/account', [
                'reauth_token' => $oauth->issueReauthToken($firstUserId),
            ])
            ->assertOk();

        $deleted = User::findOrFail($firstUserId);
        $this->assertNull($deleted->google_id);
        $this->assertNull($deleted->google_linked_at);
        $this->assertFalse($deleted->is_active);

        // Same Google identity, brand-new account.
        $second = $this->postJson('/api/v1/auth/google/complete-registration', [
            'pending_token' => $oauth->issuePendingToken([
                'google_id' => 'google-sub-1',
                'email' => 'jean@gmail.com',
                'intent' => 'face',
                'prenom' => 'Jean',
                'nom' => 'Dupont',
            ]),
            'role' => 'face',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '1995-06-15',
            'accept_cgu' => true,
        ]);

        $second->assertCreated();
        $this->assertNotSame($firstUserId, (int) $second->json('data.user.id'));
        $this->assertSame('google-sub-1', User::findOrFail((int) $second->json('data.user.id'))->google_id);
    }
}
