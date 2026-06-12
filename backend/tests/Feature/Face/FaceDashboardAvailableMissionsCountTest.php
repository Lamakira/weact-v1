<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\MissionStatus;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceDashboardAvailableMissionsCountTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create a Producer user
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    public function test_returns_count_of_available_missions_for_authenticated_face(): void
    {
        // Create published missions with future deadline
        Mission::factory()
            ->count(3)
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['count'],
                'message',
            ])
            ->assertJson([
                'data' => ['count' => 3],
                'message' => 'Available missions count retrieved successfully',
            ]);
    }

    public function test_returns_zero_when_no_available_missions_exist(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertOk()
            ->assertJson([
                'data' => ['count' => 0],
            ]);
    }

    public function test_excludes_missions_with_passed_deadline(): void
    {
        // Create mission with past deadline
        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::yesterday(),
            ]);

        // Create mission with future deadline
        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertOk()
            ->assertJson([
                'data' => ['count' => 1],
            ]);
    }

    public function test_includes_missions_with_deadline_today(): void
    {
        // Create mission with deadline today
        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today(),
            ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertOk()
            ->assertJson([
                'data' => ['count' => 1],
            ]);
    }

    public function test_only_counts_published_missions(): void
    {
        // Create missions with different statuses
        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Draft,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Closed,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Completed,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertOk()
            ->assertJson([
                'data' => ['count' => 1], // Only published mission
            ]);
    }

    public function test_returns_403_for_producer_user(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertForbidden()
            ->assertJson([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ]);
    }

    public function test_returns_401_for_unauthenticated_user(): void
    {
        $response = $this->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertUnauthorized();
    }

    public function test_counts_missions_from_all_producers(): void
    {
        // Create a second producer
        $producer2 = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer2->id,
        ]);

        // Create missions from different producers
        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        Mission::factory()
            ->for($producer2)
            ->count(2)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertOk()
            ->assertJson([
                'data' => ['count' => 3], // All producers' missions
            ]);
    }

    // ─── Exclusion des missions UGC (FR5, UGC 2.1) ───────────────────

    public function test_ugc_missions_are_excluded_from_available_count(): void
    {
        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);
        // La factory Mission ne tire jamais `ugc` — attributs explicites obligatoires.
        $this->producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => Carbon::today()->addDays(7),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertOk()
            ->assertJson([
                'data' => ['count' => 1], // La mission UGC n'est pas comptée
            ]);
    }

    // ─── Filtre producteur is_active (story 3.0) ─────────────────────

    public function test_excludes_missions_from_inactive_producer(): void
    {
        // Le badge compte ce que le listing affiche (D-3.0.e) : la mission
        // d'un producteur désactivé ne doit pas gonfler le count.
        Mission::factory()
            ->for($this->producer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        $inactiveProducer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $inactiveProducer->id,
            'is_active' => false,
        ]);
        Mission::factory()
            ->for($inactiveProducer)
            ->create([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => Carbon::today()->addDays(7),
            ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/available-missions-count');

        $response->assertOk()
            ->assertJson([
                'data' => ['count' => 1], // Seule la mission du producteur actif
            ]);
    }
}
