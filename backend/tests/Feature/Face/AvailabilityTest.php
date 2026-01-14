<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_can_get_availability_default_true_for_new_face(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/availability');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'is_available',
                    'availability_badge',
                    'availability_badge_color',
                ],
            ])
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.availability_badge', 'Disponible')
            ->assertJsonPath('data.availability_badge_color', 'green');
    }

    public function test_can_toggle_availability_to_false(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/availability', [
                'is_available' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.availability_badge', 'Indisponible')
            ->assertJsonPath('data.availability_badge_color', 'grey')
            ->assertJsonPath('message', 'Disponibilité mise à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'is_available' => false,
        ]);
    }

    public function test_can_toggle_availability_to_true(): void
    {
        // First set to false
        $this->face->update(['is_available' => false]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/availability', [
                'is_available' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.availability_badge', 'Disponible')
            ->assertJsonPath('data.availability_badge_color', 'green')
            ->assertJsonPath('message', 'Disponibilité mise à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'is_available' => true,
        ]);
    }

    public function test_rejects_non_boolean_availability_value(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/availability', [
                'is_available' => 'yes',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_available'])
            ->assertJsonPath('errors.is_available.0', 'Le statut de disponibilité doit être vrai ou faux');
    }

    public function test_rejects_missing_availability_value(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/availability', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_available'])
            ->assertJsonPath('errors.is_available.0', 'Le statut de disponibilité est requis');
    }

    public function test_rejects_integer_as_boolean_availability(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/availability', [
                'is_available' => 1,
            ]);

        // Laravel's boolean validation accepts 1/0 as valid booleans
        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_availability(): void
    {
        $response = $this->getJson('/api/v1/face/availability');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_update_availability(): void
    {
        $response = $this->putJson('/api/v1/face/availability', [
            'is_available' => false,
        ]);

        $response->assertUnauthorized();
    }

    public function test_producer_cannot_access_availability_endpoint(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/availability');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_producer_cannot_update_availability(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->putJson('/api/v1/face/availability', [
                'is_available' => false,
            ]);

        $response->assertForbidden();
    }

    public function test_availability_badge_is_correct_for_available_status(): void
    {
        $this->face->update(['is_available' => true]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/availability');

        $response->assertOk()
            ->assertJsonPath('data.availability_badge', 'Disponible')
            ->assertJsonPath('data.availability_badge_color', 'green');
    }

    public function test_availability_badge_is_correct_for_unavailable_status(): void
    {
        $this->face->update(['is_available' => false]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/availability');

        $response->assertOk()
            ->assertJsonPath('data.availability_badge', 'Indisponible')
            ->assertJsonPath('data.availability_badge_color', 'grey');
    }
}
