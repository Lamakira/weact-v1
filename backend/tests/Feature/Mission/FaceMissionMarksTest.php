<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\CandidatureStatus;
use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lot D8 : marques des cartes de missions côté Face (candidature existante, éligibilité)
 * et service d'éligibilité unique partagé avec la candidature.
 */
class FaceMissionMarksTest extends TestCase
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
        ]);
    }

    /** @return array{0: User, 1: Face} */
    private function makeFace(string $sexe, ?string $tier = 'starter'): array
    {
        $face = Face::factory()->create(['sexe' => $sexe]);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        if ($tier !== null) {
            FaceSubscription::factory()->{$tier}()->active()->create(['face_id' => $face->id]);
        }

        return [$user, $face];
    }

    /** @param array<string, mixed> $overrides */
    private function makeMission(array $overrides = []): Mission
    {
        return Mission::factory()->published()->create(array_merge([
            'producer_id' => $this->producer->id,
            'genre_voulu' => MissionGender::Tous,
            'type_mission' => 'publicite',
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function makeUgcMission(array $overrides = []): Mission
    {
        return $this->producer->missions()->create(array_merge([
            'titre' => 'Appel UGC',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ], $overrides));
    }

    // ---- Service d'éligibilité, cas par cas ----

    public function test_service_ok_for_subscribed_face_and_compatible_gender(): void
    {
        [, $face] = $this->makeFace('femme');
        $service = app(MissionEligibility::class);

        $this->assertSame('ok', $service->evaluate($face, $this->makeMission(['genre_voulu' => MissionGender::Femme])));
        $this->assertSame('ok', $service->evaluate($face, $this->makeMission()));
    }

    public function test_service_gender_mismatch(): void
    {
        [, $face] = $this->makeFace('femme');
        $service = app(MissionEligibility::class);

        $this->assertSame('gender_mismatch', $service->evaluate($face, $this->makeMission(['genre_voulu' => MissionGender::Homme])));
    }

    public function test_service_gender_mismatch_when_face_has_no_gender(): void
    {
        [, $face] = $this->makeFace('femme');
        $face->sexe = null;

        $this->assertSame('gender_mismatch', app(MissionEligibility::class)
            ->evaluate($face, $this->makeMission(['genre_voulu' => MissionGender::Homme])));
    }

    public function test_service_subscription_required_for_free_face_on_standard_mission(): void
    {
        [, $face] = $this->makeFace('femme', null);

        $this->assertSame('subscription_required', app(MissionEligibility::class)->evaluate($face, $this->makeMission()));
    }

    public function test_service_subscription_takes_precedence_over_gender(): void
    {
        [, $face] = $this->makeFace('femme', null);

        $this->assertSame('subscription_required', app(MissionEligibility::class)
            ->evaluate($face, $this->makeMission(['genre_voulu' => MissionGender::Homme])));
    }

    public function test_service_ugc_subscription_required_for_starter_face(): void
    {
        [, $face] = $this->makeFace('femme', 'starter');

        $this->assertSame('ugc_subscription_required', app(MissionEligibility::class)->evaluate($face, $this->makeUgcMission()));
    }

    public function test_service_ok_for_pro_face_on_ugc_mission(): void
    {
        [, $face] = $this->makeFace('femme', 'pro');

        $this->assertSame('ok', app(MissionEligibility::class)->evaluate($face, $this->makeUgcMission()));
    }

    // ---- Liste standard ----

    public function test_standard_list_exposes_my_candidature_and_eligibility(): void
    {
        [$user, $face] = $this->makeFace('femme');
        $applied = $this->makeMission();
        $other = $this->makeMission();
        Candidature::factory()->create(['face_id' => $face->id, 'mission_id' => $applied->id, 'status' => CandidatureStatus::Pending]);

        $items = collect($this->actingAs($user)->getJson('/api/v1/face/missions')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('pending', $items[$applied->uuid]['my_candidature']);
        $this->assertSame('En attente', $items[$applied->uuid]['my_candidature_label']);
        $this->assertNull($items[$other->uuid]['my_candidature']);
        $this->assertSame('ok', $items[$other->uuid]['eligibility']);
    }

    public function test_standard_list_marks_subscription_required_for_free_face(): void
    {
        [$user] = $this->makeFace('femme', null);
        $this->makeMission();

        $this->actingAs($user)->getJson('/api/v1/face/missions')
            ->assertOk()
            ->assertJsonPath('data.0.eligibility', 'subscription_required');
    }

    public function test_standard_list_puts_gender_mismatch_last_keeping_order_and_pagination(): void
    {
        [$user] = $this->makeFace('femme');
        // Du plus récent au plus ancien : M1 (homme), F1, M2 (homme), F2, ... 14 missions
        $expected = [];
        for ($i = 0; $i < 14; $i++) {
            $mismatch = $i % 2 === 0;
            $m = $this->makeMission([
                'genre_voulu' => $mismatch ? MissionGender::Homme : MissionGender::Femme,
                'created_at' => now()->subMinutes($i),
            ]);
            $expected[] = ['uuid' => $m->uuid, 'mismatch' => $mismatch];
        }
        $compatible = array_column(array_filter($expected, fn ($e) => ! $e['mismatch']), 'uuid');
        $mismatching = array_column(array_filter($expected, fn ($e) => $e['mismatch']), 'uuid');

        $page1 = $this->actingAs($user)->getJson('/api/v1/face/missions?page=1')->assertOk();
        $page2 = $this->actingAs($user)->getJson('/api/v1/face/missions?page=2')->assertOk();

        $ids = array_merge(array_column($page1->json('data'), 'id'), array_column($page2->json('data'), 'id'));
        $this->assertSame(array_merge($compatible, $mismatching), $ids);
        $this->assertSame(14, $page1->json('meta.total'));
        $this->assertSame('gender_mismatch', $page2->json('data.1.eligibility'));
        $this->assertSame('ok', $page1->json('data.0.eligibility'));
    }

    public function test_standard_list_has_no_n_plus_one_on_candidatures(): void
    {
        [$user, $face] = $this->makeFace('femme');
        foreach (range(1, 6) as $i) {
            $m = $this->makeMission();
            Candidature::factory()->create(['face_id' => $face->id, 'mission_id' => $m->id]);
        }

        DB::enableQueryLog();
        $this->actingAs($user)->getJson('/api/v1/face/missions')->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'select `mission_id`, `status`, `accepted_at` from `candidatures`'));
        DB::disableQueryLog();

        // Une seule requête de candidatures pour toute la page (le withCount est une sous-requête de la requête missions).
        $this->assertCount(1, $queries);
    }

    public function test_my_candidature_is_isolated_between_faces(): void
    {
        [, $faceA] = $this->makeFace('femme');
        [$userB] = $this->makeFace('femme');
        $mission = $this->makeMission();
        Candidature::factory()->create(['face_id' => $faceA->id, 'mission_id' => $mission->id]);

        $this->actingAs($userB)->getJson('/api/v1/face/missions')
            ->assertOk()
            ->assertJsonPath('data.0.my_candidature', null);
    }

    // ---- Liste UGC ----

    public function test_ugc_list_exposes_marks_and_orders_gender_mismatch_last(): void
    {
        [$user, $face] = $this->makeFace('femme', 'pro');
        $mismatch = $this->makeUgcMission(['genre_voulu' => 'homme', 'created_at' => now()]);
        $applied = $this->makeUgcMission(['created_at' => now()->subMinute()]);
        Candidature::factory()->create(['face_id' => $face->id, 'mission_id' => $applied->id, 'status' => CandidatureStatus::Pending]);

        $response = $this->actingAs($user)->getJson('/api/v1/face/ugc/missions')->assertOk();

        $this->assertSame([$applied->uuid, $mismatch->uuid], array_column($response->json('data'), 'id'));
        $this->assertSame('pending', $response->json('data.0.my_candidature'));
        $this->assertSame('ok', $response->json('data.0.eligibility'));
        $this->assertNull($response->json('data.1.my_candidature'));
        $this->assertSame('gender_mismatch', $response->json('data.1.eligibility'));
    }

    public function test_ugc_teaser_list_does_not_expose_marks(): void
    {
        [$user] = $this->makeFace('femme', 'starter');
        $this->makeUgcMission();

        $item = $this->actingAs($user)->getJson('/api/v1/face/ugc/missions')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('eligibility', $item);
        $this->assertArrayNotHasKey('my_candidature', $item);
    }

    // ---- Candidature inchangée (codes / statuts via le service) ----

    public function test_apply_gender_mismatch_without_profile_gender_keeps_message(): void
    {
        [$user, $face] = $this->makeFace('femme');
        $face->forceFill(['sexe' => null])->save();
        $mission = $this->makeMission(['genre_voulu' => MissionGender::Homme]);

        $this->actingAs($user)->postJson("/api/v1/face/missions/{$mission->uuid}/apply", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'gender_mismatch')
            ->assertJsonPath('error.message', 'Veuillez compléter votre profil (genre) avant de postuler.');
    }
}
