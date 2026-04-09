<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
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
            'username' => 'johnfirst',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'sexe' => 'homme',
            'date_naissance' => '1995-06-15',
            'nationalite' => 'Béninoise',
            'pays' => 'Bénin',
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
            'username' => 'alicemartin',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'sexe' => 'femme',
            'date_naissance' => '1998-04-10',
            'nationalite' => 'Béninoise',
            'pays' => 'Bénin',
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
}
