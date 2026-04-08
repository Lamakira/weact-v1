<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBrowseMissionsTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
            'is_active' => true,
        ]);
    }

    public function test_type_mission_filter_returns_only_matching_public_missions(): void
    {
        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Mission publicite',
            'type_mission' => MissionType::Publicite,
        ]);

        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Mission film',
            'type_mission' => MissionType::Film,
        ]);

        $response = $this->getJson('/api/v1/public/missions?type_mission=publicite');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Mission publicite')
            ->assertJsonPath('data.0.type_mission', 'publicite');
    }

    public function test_lieu_filter_supports_partial_case_insensitive_search(): void
    {
        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Mission Cotonou',
            'lieu' => 'Cotonou, Bénin',
        ]);

        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Mission Parakou',
            'lieu' => 'Parakou, Bénin',
        ]);

        $response = $this->getJson('/api/v1/public/missions?lieu=coTo');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Mission Cotonou');
    }

    public function test_budget_range_filters_apply_min_and_max_bounds(): void
    {
        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Budget trop bas',
            'budget' => 50000,
        ]);

        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Budget retenu',
            'budget' => 150000,
        ]);

        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Budget trop haut',
            'budget' => 300000,
        ]);

        $response = $this->getJson('/api/v1/public/missions?budget_min=100000&budget_max=200000');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Budget retenu');
    }

    public function test_budget_max_can_be_used_without_budget_min(): void
    {
        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Budget acceptable',
            'budget' => 120000,
        ]);

        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Budget trop eleve',
            'budget' => 280000,
        ]);

        $response = $this->getJson('/api/v1/public/missions?budget_max=150000');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Budget acceptable');
    }

    public function test_multiple_filters_can_be_combined(): void
    {
        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Match exact',
            'type_mission' => MissionType::Film,
            'lieu' => 'Cotonou, Bénin',
            'budget' => 175000,
        ]);

        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Mauvais lieu',
            'type_mission' => MissionType::Film,
            'lieu' => 'Porto-Novo, Bénin',
            'budget' => 175000,
        ]);

        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Mauvais type',
            'type_mission' => MissionType::Publicite,
            'lieu' => 'Cotonou, Bénin',
            'budget' => 175000,
        ]);

        $response = $this->getJson('/api/v1/public/missions?type_mission=film&lieu=cotonou&budget_min=150000&budget_max=200000');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Match exact');
    }

    public function test_invalid_filter_parameters_return_validation_errors(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->getJson('/api/v1/public/missions?type_mission=invalid&budget_min=-5&budget_max=3');

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'type_mission',
                'budget_min',
            ]);
    }

    public function test_budget_max_less_than_budget_min_returns_french_validation_message(): void
    {
        $response = $this->getJson('/api/v1/public/missions?budget_min=200000&budget_max=100000');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_max'])
            ->assertJsonPath(
                'errors.budget_max.0',
                'Le budget maximum doit être supérieur ou égal au budget minimum.'
            );
    }
}
