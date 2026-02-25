<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguesTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_can_get_langues(): void
    {
        $this->face->update([
            'langues' => ['Français', 'Anglais', 'Fon'],
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/langues');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['langues'],
            ])
            ->assertJsonPath('data.langues', ['Français', 'Anglais', 'Fon']);
    }

    public function test_get_langues_returns_null_when_not_set(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/langues');

        $response->assertOk()
            ->assertJsonPath('data.langues', null);
    }

    public function test_can_update_langues_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => ['Français', 'Anglais'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.langues', ['Français', 'Anglais'])
            ->assertJsonPath('message', 'Langues mises à jour avec succès');

        $this->face->refresh();
        $this->assertEquals(['Français', 'Anglais'], $this->face->langues);
    }

    public function test_can_clear_langues_with_null(): void
    {
        $this->face->update(['langues' => ['Français']]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.langues', null);

        $this->face->refresh();
        $this->assertNull($this->face->langues);
    }

    public function test_can_clear_langues_with_empty_array(): void
    {
        $this->face->update(['langues' => ['Français']]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => [],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.langues', null);

        $this->face->refresh();
        $this->assertNull($this->face->langues);
    }

    public function test_rejects_more_than_10_langues(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8', 'L9', 'L10', 'L11'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['langues'])
            ->assertJsonPath('errors.langues.0', 'Vous ne pouvez pas ajouter plus de 10 langues.');
    }

    public function test_rejects_langue_exceeding_50_characters(): void
    {
        $longLangue = str_repeat('a', 51);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => [$longLangue],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['langues.0']);
    }

    public function test_rejects_duplicate_langues(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => ['Français', 'Français'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['langues.1']);
    }

    public function test_rejects_empty_string_langue_items(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => ['', 'Français'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['langues.0']);
    }

    public function test_trims_whitespace_from_langues(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => ['  Français  ', '  Anglais  '],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.langues', ['Français', 'Anglais']);

        $this->face->refresh();
        $this->assertEquals(['Français', 'Anglais'], $this->face->langues);
    }

    public function test_rejects_non_string_langue_items(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => [123, true],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['langues.0']);
    }

    public function test_producer_cannot_access_langues_endpoint(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/langues');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_producer_cannot_update_langues(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->putJson('/api/v1/face/langues', [
                'langues' => ['Français'],
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_langues(): void
    {
        $response = $this->getJson('/api/v1/face/langues');

        $response->assertUnauthorized();
    }
}
