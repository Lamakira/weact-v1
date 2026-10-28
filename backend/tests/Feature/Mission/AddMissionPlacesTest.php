<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Events\MissionPublished;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Ajout de places sur une mission UGC (publiée ou fermée parce que pleine) : gratuit,
 * sans événement de publication, rouvre la mission même après la date limite.
 */
class AddMissionPlacesTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    public function test_adds_places_to_full_closed_product_only_mission_and_allows_accepting_pending(): void
    {
        [$mission, $pending] = $this->makeFullClosedMission();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", ['places' => 2])
            ->assertOk()
            ->assertJsonPath('message', '2 places ajoutées.')
            ->assertJsonPath('data.nombre_faces_voulu', 3)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.can_add_places', true)
            ->assertJsonStructure(['data' => ['summary']]);

        $this->assertDatabaseHas('missions', ['id' => $mission->id, 'nombre_faces_voulu' => 3, 'status' => 'published']);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$pending[0]->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_reopens_even_when_deadline_is_passed_and_allows_accepting_pending(): void
    {
        [$mission, $pending] = $this->makeFullClosedMission(['date_limite_candidature' => now()->subDays(3)]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", ['places' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertFalse($mission->fresh()->isAcceptingCandidatures());

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$pending[0]->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_adding_one_place_to_published_mission_uses_singular_message(): void
    {
        $mission = $this->makeUgcMission(['nombre_faces_voulu' => 3]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", ['places' => 1])
            ->assertOk()
            ->assertJsonPath('message', '1 place ajoutée.')
            ->assertJsonPath('data.nombre_faces_voulu', 4)
            ->assertJsonPath('data.status', 'published');

        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
    }

    public function test_hybrid_closed_mission_is_reopened_without_any_payment(): void
    {
        $mission = $this->makeUgcMission([
            'status' => MissionStatus::Closed,
            'type_compensation' => CompensationType::Hybrid->value,
            'montant_remuneration' => 10000,
        ]);
        $before = DB::table('mission_payment_candidatures')->count();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", ['places' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.nombre_faces_voulu', 2);

        $this->assertSame($before, DB::table('mission_payment_candidatures')->count());
        $this->assertDatabaseCount('mission_payments', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function refusedMissionProvider(): array
    {
        return [
            'pending_payment' => [['status' => MissionStatus::PendingPayment, 'commission_paid_at' => null]],
            'completed' => [['status' => MissionStatus::Completed]],
            'closed remboursée' => [['status' => MissionStatus::Closed, 'commission_refunded_at' => '2026-10-01 10:00:00']],
            'non UGC' => [[
                'type_mission' => MissionType::Publicite->value,
                'type_compensation' => null,
                'commission_ugc' => null,
                'nom_produit' => null,
                'valeur_produit' => null,
                'nombre_videos' => null,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedMissionProvider')]
    public function test_refuses_missions_that_cannot_receive_places(array $overrides): void
    {
        $mission = $this->makeUgcMission($overrides);
        $status = $mission->status;

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", ['places' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mission');

        $fresh = $mission->fresh();
        $this->assertSame(1, $fresh->nombre_faces_voulu);
        $this->assertSame($status, $fresh->status);
    }

    /**
     * @param  mixed  $payload
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPlacesProvider')]
    public function test_validates_places(array $payload): void
    {
        $mission = $this->makeUgcMission();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('places');

        $this->assertSame(1, $mission->fresh()->nombre_faces_voulu);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidPlacesProvider(): array
    {
        return [
            'absent' => [[]],
            'zéro' => [['places' => 0]],
            'vingt et un' => [['places' => 21]],
            'non entier' => [['places' => 'abc']],
            'décimal' => [['places' => 1.5]],
        ];
    }

    public function test_other_producer_gets_403(): void
    {
        $mission = $this->makeUgcMission();
        $otherProducer = Producer::factory()->create();
        $other = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $otherProducer->id]);

        $this->actingAs($other)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", ['places' => 1])
            ->assertForbidden();

        $this->assertSame(1, $mission->fresh()->nombre_faces_voulu);
    }

    public function test_face_user_gets_403(): void
    {
        $mission = $this->makeUgcMission();
        $face = Face::factory()->create();
        $faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $this->actingAs($faceUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", ['places' => 1])
            ->assertForbidden();

        $this->assertSame(1, $mission->fresh()->nombre_faces_voulu);
    }

    public function test_show_exposes_can_add_places_to_producer_only(): void
    {
        $ugc = $this->makeUgcMission();
        $standard = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$ugc->uuid}")
            ->assertOk()
            ->assertJsonPath('data.can_add_places', true);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$standard->uuid}")
            ->assertOk()
            ->assertJsonPath('data.can_add_places', false);

        $face = Face::factory()->create();
        $faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);

        $response = $this->actingAs($faceUser)->getJson("/api/v1/face/missions/{$standard->uuid}");
        $response->assertOk();
        $this->assertArrayNotHasKey('can_add_places', $response->json('data'));
    }

    public function test_adding_places_does_not_dispatch_mission_published(): void
    {
        Event::fake([MissionPublished::class]);
        [$mission] = $this->makeFullClosedMission();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/add-places", ['places' => 1])
            ->assertOk();

        Event::assertNotDispatched(MissionPublished::class);
    }

    /**
     * Mission UGC produit seul fermée, 1 place, 1 candidature confirmée + 2 en attente.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{0: Mission, 1: list<Candidature>}
     */
    private function makeFullClosedMission(array $overrides = []): array
    {
        $mission = $this->makeUgcMission(array_merge(['status' => MissionStatus::Closed], $overrides));

        $this->makeCandidature($mission, CandidatureStatus::Confirmed);
        $pending = [
            $this->makeCandidature($mission, CandidatureStatus::Pending),
            $this->makeCandidature($mission, CandidatureStatus::Pending),
        ];

        return [$mission, $pending];
    }

    private function makeCandidature(Mission $mission, CandidatureStatus $status): Candidature
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);

        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUgcMission(array $overrides = []): Mission
    {
        $mission = $this->producer->missions()->create(array_merge([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'Brief',
            'date_tournage' => null,
            'lieu' => null,
            'duree' => null,
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => MissionType::Ugc->value,
            'genre_voulu' => 'tous',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => CompensationType::Product->value,
            'nom_produit' => 'Sneakers',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ], $overrides));

        // Une mission publiée ou fermée a déjà été publiée : l'observateur ne ré-émet MissionPublished
        // que pour la toute première publication (published_at nul).
        if (in_array($mission->status, [MissionStatus::Published, MissionStatus::Closed], true)) {
            $mission->forceFill(['published_at' => now()->subDay()])->saveQuietly();
        }

        return $mission;
    }
}
