<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BioTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;
    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create([
            'bio' => 'Initial bio content',
        ]);
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    public function test_producer_can_get_their_bio(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/profile/bio');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['bio'],
            ])
            ->assertJsonPath('data.bio', 'Initial bio content');
    }

    public function test_producer_can_update_their_bio(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/profile/bio', [
                'bio' => 'Updated bio content',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['bio'],
                'message',
            ])
            ->assertJsonPath('data.bio', 'Updated bio content')
            ->assertJsonPath('message', 'Bio mise à jour avec succès');

        $this->assertDatabaseHas('producers', [
            'id' => $this->producer->id,
            'bio' => 'Updated bio content',
        ]);
    }

    public function test_producer_can_clear_their_bio_with_null(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/profile/bio', [
                'bio' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.bio', null);

        $this->assertDatabaseHas('producers', [
            'id' => $this->producer->id,
            'bio' => null,
        ]);
    }

    public function test_producer_can_clear_their_bio_with_empty_string(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/profile/bio', [
                'bio' => '',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.bio', null);

        // Empty string is converted to null for consistency
        $this->assertDatabaseHas('producers', [
            'id' => $this->producer->id,
            'bio' => null,
        ]);
    }

    public function test_bio_exceeding_500_characters_returns_validation_error(): void
    {
        $longBio = str_repeat('a', 501);

        $response = $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/profile/bio', [
                'bio' => $longBio,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bio'])
            ->assertJsonFragment([
                'bio' => ['La bio ne peut pas dépasser 500 caractères'],
            ]);
    }

    public function test_bio_with_exactly_500_characters_is_valid(): void
    {
        $maxBio = str_repeat('a', 500);

        $response = $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/profile/bio', [
                'bio' => $maxBio,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.bio', $maxBio);
    }

    public function test_unauthenticated_user_cannot_get_bio(): void
    {
        $response = $this->getJson('/api/v1/producer/profile/bio');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_update_bio(): void
    {
        $response = $this->putJson('/api/v1/producer/profile/bio', [
            'bio' => 'Test bio',
        ]);

        $response->assertUnauthorized();
    }

    public function test_face_user_cannot_access_producer_bio_endpoint(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/producer/profile/bio');

        $response->assertForbidden();
    }

    public function test_face_user_cannot_update_producer_bio(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->putJson('/api/v1/producer/profile/bio', [
                'bio' => 'Test bio',
            ]);

        $response->assertForbidden();
    }
}
