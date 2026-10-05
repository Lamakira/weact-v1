<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `users.google_id` is the identity the whole Google flow trusts: it must only ever
 * be written explicitly, and never be shared by two accounts.
 */
class GoogleIdColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_id_is_not_mass_assignable(): void
    {
        $created = User::create([
            'email' => 'created@example.com',
            'password' => 'Password123',
            'google_id' => 'attacker-sub',
            'google_linked_at' => now(),
        ]);

        $this->assertNull($created->fresh()->google_id);
        $this->assertNull($created->fresh()->google_linked_at);

        $filled = (new User)->fill(['google_id' => 'attacker-sub']);

        $this->assertNull($filled->google_id);
    }

    public function test_two_users_cannot_share_a_google_id(): void
    {
        User::factory()->create()->forceFill(['google_id' => 'google-sub-1'])->save();

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create()->forceFill(['google_id' => 'google-sub-1'])->save();
    }
}
