<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProducerViewCandidaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Producer with User
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create a published mission owned by this Producer
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create a Face with User for candidatures
        $this->face = Face::factory()->create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'ville' => 'Cotonou',
            'tarif_horaire' => 25000,
            'tarif_journalier' => 150000,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_producer_can_view_candidatures_for_their_mission(): void
    {
        // Create candidatures for this mission (each with different face due to unique constraint)
        $faces = Face::factory()->count(3)->create();
        foreach ($faces as $face) {
            Candidature::factory()->create([
                'mission_id' => $this->mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Pending,
            ]);
        }

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'status_label',
                        'message_motivation',
                        'created_at',
                        'face' => [
                            'id',
                            'display_name',
                            'profile_photo_url',
                            'category',
                            'city',
                            'tarif_horaire',
                            'tarif_journalier',
                        ],
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_returns_empty_array_when_mission_has_no_candidatures(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_paginates_candidatures_at_15_per_page(): void
    {
        // Create 20 candidatures
        $faces = Face::factory()->count(20)->create();
        foreach ($faces as $face) {
            Candidature::factory()->create([
                'mission_id' => $this->mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Pending,
            ]);
        }

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 2);

        // Check second page
        $response2 = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures?page=2");

        $response2->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_filters_candidatures_by_status(): void
    {
        // Create candidatures with different statuses
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $face2 = Face::factory()->create();
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $face2->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        $face3 = Face::factory()->create();
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $face3->id,
            'status' => CandidatureStatus::Rejected,
        ]);

        // Filter by pending
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures?status=pending");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');

        // Filter by accepted
        $response2 = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures?status=accepted");

        $response2->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'accepted');
    }

    public function test_returns_422_for_invalid_status_filter(): void
    {
        // Create candidatures (each with different face due to unique constraint)
        $faces = Face::factory()->count(3)->create();
        foreach ($faces as $face) {
            Candidature::factory()->create([
                'mission_id' => $this->mission->id,
                'face_id' => $face->id,
            ]);
        }

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures?status=invalid_status");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_includes_face_data_with_correct_fields(): void
    {
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
            'message_motivation' => 'Je suis très motivée pour cette mission.',
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertOk()
            ->assertJsonPath('data.0.face.id', $this->face->uuid)
            ->assertJsonPath('data.0.face.display_name', 'Marie Dupont')
            ->assertJsonPath('data.0.face.city', 'Cotonou')
            ->assertJsonPath('data.0.face.tarif_horaire', 25000)
            ->assertJsonPath('data.0.face.tarif_journalier', 150000);
    }

    public function test_truncates_motivation_message_to_150_characters(): void
    {
        $longMessage = str_repeat('A', 200);

        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'message_motivation' => $longMessage,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertOk();

        $motivation = $response->json('data.0.message_motivation');
        $this->assertLessThanOrEqual(153, strlen($motivation)); // 150 + '...'
    }

    public function test_returns_candidatures_ordered_by_most_recent_first(): void
    {
        $faces = Face::factory()->count(3)->create();

        $candidature1 = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $faces[0]->id,
            'created_at' => now()->subDays(2),
        ]);

        $candidature2 = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $faces[1]->id,
            'created_at' => now()->subDay(),
        ]);

        $candidature3 = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $faces[2]->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals([$candidature3->uuid, $candidature2->uuid, $candidature1->uuid], $ids);
    }

    public function test_returns_403_when_face_tries_to_access_producer_candidatures(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertForbidden();
    }

    public function test_returns_403_when_producer_tries_to_view_another_producer_mission_candidatures(): void
    {
        // Create another producer
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($otherProducerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Cette mission ne vous appartient pas');
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertUnauthorized();
    }

    public function test_returns_404_when_mission_does_not_exist(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions/00000000-0000-0000-0000-000000000000/candidatures');

        $response->assertNotFound();
    }

    public function test_filters_by_all_candidature_statuses(): void
    {
        $statuses = [
            CandidatureStatus::Pending,
            CandidatureStatus::Accepted,
            CandidatureStatus::Confirmed,
            CandidatureStatus::InProgress,
            CandidatureStatus::Completed,
            CandidatureStatus::Rejected,
        ];

        foreach ($statuses as $status) {
            $face = Face::factory()->create();
            Candidature::factory()->create([
                'mission_id' => $this->mission->id,
                'face_id' => $face->id,
                'status' => $status,
            ]);
        }

        foreach ($statuses as $status) {
            $response = $this->actingAs($this->producerUser)
                ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures?status={$status->value}");

            $response->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.status', $status->value);
        }
    }

    public function test_only_returns_candidatures_for_the_specified_mission(): void
    {
        // Create candidature for our mission
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
        ]);

        // Create another mission with candidatures (same producer)
        $otherMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
        ]);
        $otherFace = Face::factory()->create();
        Candidature::factory()->create([
            'mission_id' => $otherMission->id,
            'face_id' => $otherFace->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.face.id', $this->face->uuid);
    }

    // ===================================================================
    // FP-2.12 face.has_elite_badge in producer candidatures listing
    // ===================================================================

    public function test_face_has_elite_badge_true_for_elite_candidate(): void
    {
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $this->face->id]);
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures")
            ->assertOk()
            ->assertJsonPath('data.0.face.has_elite_badge', true);
    }

    public function test_face_has_elite_badge_false_for_pro_candidate(): void
    {
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $this->face->id]);
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures")
            ->assertOk()
            ->assertJsonPath('data.0.face.has_elite_badge', false);
    }

    public function test_face_has_elite_badge_false_for_starter_candidate(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures")
            ->assertOk()
            ->assertJsonPath('data.0.face.has_elite_badge', false);
    }

    public function test_face_has_elite_badge_false_for_free_candidate(): void
    {
        // $this->face has no FaceSubscription row → Free fallback.
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures")
            ->assertOk()
            ->assertJsonPath('data.0.face.has_elite_badge', false);
    }

    public function test_listing_eager_loads_face_active_subscription_to_prevent_n_plus_one(): void
    {
        // Three distinct Faces with different tiers, all candidating on $this->mission.
        $facePro = Face::factory()->create();
        $faceElite = Face::factory()->create();

        FaceSubscription::factory()->pro()->active()->create(['face_id' => $facePro->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $faceElite->id]);

        foreach ([$this->face, $facePro, $faceElite] as $f) {
            Candidature::factory()->create([
                'mission_id' => $this->mission->id,
                'face_id' => $f->id,
                'status' => CandidatureStatus::Pending,
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures");

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();

        // The query count must stay bounded regardless of candidature count.
        // Baseline (3 candidatures) is 10 queries: Sanctum insert + auth lookups (5) +
        // pagination count + candidatures select + eager faces + eager active_subscriptions
        // (1 query via HasOne ofMany — NOT N+1) + eager conversations.
        // Without the eager-load on `face.activeSubscription`, every candidature would
        // trigger an extra SELECT in FaceEntitlementService::resolveActiveSubscription()
        // (3 candidatures → 3 extra queries → 13 total). The ceiling of 11 gives a
        // 1-query safety margin against minor Sanctum / pagination drift while still
        // failing loud if N+1 is reintroduced.
        $this->assertLessThanOrEqual(
            11,
            $queryCount,
            "Query count regressed to {$queryCount} — eager-load on face.activeSubscription likely missing."
        );
    }
}
