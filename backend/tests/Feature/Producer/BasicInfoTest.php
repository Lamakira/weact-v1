<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

use App\Enums\ProducerType;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Producer $agency;

    private User $particulierUser;

    private Producer $particulier;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an agency producer
        $this->agency = Producer::factory()->create([
            'type' => ProducerType::Agency,
            'agency_name' => 'Production ABC',
            'first_name' => null,
            'last_name' => null,
        ]);
        $this->agencyUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->agency->id,
        ]);

        // Create a particulier producer
        $this->particulier = Producer::factory()->create([
            'type' => ProducerType::Particulier,
            'agency_name' => null,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
        ]);
        $this->particulierUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->particulier->id,
        ]);
    }

    // ========== Agency Tests ==========

    public function test_agency_can_get_basic_info(): void
    {
        $response = $this->actingAs($this->agencyUser)
            ->getJson('/api/v1/producer/basic-info');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'agency_name',
                ],
            ])
            ->assertJsonPath('data.type', 'agency')
            ->assertJsonPath('data.agency_name', 'Production ABC');
    }

    public function test_agency_can_update_agency_name(): void
    {
        $response = $this->actingAs($this->agencyUser)
            ->putJson('/api/v1/producer/basic-info', [
                'agency_name' => 'New Production Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.agency_name', 'New Production Name')
            ->assertJsonPath('message', 'Informations mises à jour avec succès');

        $this->assertDatabaseHas('producers', [
            'id' => $this->agency->id,
            'agency_name' => 'New Production Name',
        ]);
    }

    public function test_agency_rejects_empty_agency_name(): void
    {
        $response = $this->actingAs($this->agencyUser)
            ->putJson('/api/v1/producer/basic-info', [
                'agency_name' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['agency_name'])
            ->assertJsonPath('errors.agency_name.0', "Le nom de l'agence est obligatoire");
    }

    public function test_agency_rejects_agency_name_exceeding_100_characters(): void
    {
        $longName = str_repeat('a', 101);

        $response = $this->actingAs($this->agencyUser)
            ->putJson('/api/v1/producer/basic-info', [
                'agency_name' => $longName,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['agency_name'])
            ->assertJsonPath('errors.agency_name.0', "Le nom de l'agence ne peut pas dépasser 100 caractères");
    }

    public function test_agency_accepts_agency_name_with_exactly_100_characters(): void
    {
        $exactName = str_repeat('a', 100);

        $response = $this->actingAs($this->agencyUser)
            ->putJson('/api/v1/producer/basic-info', [
                'agency_name' => $exactName,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('producers', [
            'id' => $this->agency->id,
            'agency_name' => $exactName,
        ]);
    }

    // ========== Particulier Tests ==========

    public function test_particulier_can_get_basic_info(): void
    {
        $response = $this->actingAs($this->particulierUser)
            ->getJson('/api/v1/producer/basic-info');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'first_name',
                    'last_name',
                ],
            ])
            ->assertJsonPath('data.type', 'particulier')
            ->assertJsonPath('data.first_name', 'Marie')
            ->assertJsonPath('data.last_name', 'Martin');
    }

    public function test_particulier_can_update_first_name(): void
    {
        $response = $this->actingAs($this->particulierUser)
            ->putJson('/api/v1/producer/basic-info', [
                'first_name' => 'Sophie',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Sophie')
            ->assertJsonPath('message', 'Informations mises à jour avec succès');

        $this->assertDatabaseHas('producers', [
            'id' => $this->particulier->id,
            'first_name' => 'Sophie',
        ]);
    }

    public function test_particulier_can_update_last_name(): void
    {
        $response = $this->actingAs($this->particulierUser)
            ->putJson('/api/v1/producer/basic-info', [
                'last_name' => 'Dupont',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.last_name', 'Dupont')
            ->assertJsonPath('message', 'Informations mises à jour avec succès');

        $this->assertDatabaseHas('producers', [
            'id' => $this->particulier->id,
            'last_name' => 'Dupont',
        ]);
    }

    public function test_particulier_can_update_both_names(): void
    {
        $response = $this->actingAs($this->particulierUser)
            ->putJson('/api/v1/producer/basic-info', [
                'first_name' => 'Sophie',
                'last_name' => 'Dupont',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Sophie')
            ->assertJsonPath('data.last_name', 'Dupont');

        $this->assertDatabaseHas('producers', [
            'id' => $this->particulier->id,
            'first_name' => 'Sophie',
            'last_name' => 'Dupont',
        ]);
    }

    public function test_particulier_rejects_empty_first_name(): void
    {
        $response = $this->actingAs($this->particulierUser)
            ->putJson('/api/v1/producer/basic-info', [
                'first_name' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name'])
            ->assertJsonPath('errors.first_name.0', 'Le prénom est obligatoire');
    }

    public function test_particulier_rejects_empty_last_name(): void
    {
        $response = $this->actingAs($this->particulierUser)
            ->putJson('/api/v1/producer/basic-info', [
                'last_name' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name'])
            ->assertJsonPath('errors.last_name.0', 'Le nom est obligatoire');
    }

    public function test_particulier_rejects_first_name_exceeding_100_characters(): void
    {
        $longName = str_repeat('a', 101);

        $response = $this->actingAs($this->particulierUser)
            ->putJson('/api/v1/producer/basic-info', [
                'first_name' => $longName,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name'])
            ->assertJsonPath('errors.first_name.0', 'Le prénom ne peut pas dépasser 100 caractères');
    }

    public function test_particulier_rejects_last_name_exceeding_100_characters(): void
    {
        $longName = str_repeat('a', 101);

        $response = $this->actingAs($this->particulierUser)
            ->putJson('/api/v1/producer/basic-info', [
                'last_name' => $longName,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name'])
            ->assertJsonPath('errors.last_name.0', 'Le nom ne peut pas dépasser 100 caractères');
    }

    // ========== Authorization Tests ==========

    public function test_face_cannot_access_producer_basic_info(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/producer/basic-info');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Producteurs');
    }

    public function test_face_cannot_update_producer_basic_info(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->putJson('/api/v1/producer/basic-info', [
                'agency_name' => 'Test',
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_basic_info(): void
    {
        $response = $this->getJson('/api/v1/producer/basic-info');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_update_basic_info(): void
    {
        $response = $this->putJson('/api/v1/producer/basic-info', [
            'agency_name' => 'Test',
        ]);

        $response->assertUnauthorized();
    }
}
