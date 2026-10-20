<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Enums\CandidatureStatus;
use App\Mail\MissionCompletedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lot C8 : `can_rate` / `my_rating` exposés à la Face sur le détail mission et « Mes candidatures ».
 */
class FaceRatingPayloadTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    private function completedCandidature(): Candidature
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);

        return Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);
    }

    private function rate(Candidature $candidature, int $score = 4, ?string $comment = 'Très bien'): Rating
    {
        return Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => $score,
            'comment' => $comment,
        ]);
    }

    public function test_mission_detail_exposes_can_rate_true_for_unrated_completed_candidature(): void
    {
        $candidature = $this->completedCandidature();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$candidature->mission->uuid}")
            ->assertOk()
            ->assertJsonPath('candidature.can_rate', true)
            ->assertJsonPath('candidature.my_rating', null);
    }

    public function test_mission_detail_exposes_my_rating_and_can_rate_false_once_rated(): void
    {
        $candidature = $this->completedCandidature();
        $this->rate($candidature, 4, 'Très bien');

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$candidature->mission->uuid}")
            ->assertOk()
            ->assertJsonPath('candidature.can_rate', false)
            ->assertJsonPath('candidature.my_rating.score', 4)
            ->assertJsonPath('candidature.my_rating.comment', 'Très bien')
            ->assertJsonStructure(['candidature' => ['my_rating' => ['score', 'comment', 'created_at']]]);
    }

    public function test_mission_detail_can_rate_false_when_candidature_not_completed(): void
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('candidature.can_rate', false)
            ->assertJsonPath('candidature.my_rating', null);
    }

    public function test_producer_rating_of_the_face_is_not_exposed_as_my_rating(): void
    {
        $candidature = $this->completedCandidature();
        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->face->id,
            'rated_type' => Face::class,
            'score' => 2,
            'comment' => 'Note du producteur',
        ]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$candidature->mission->uuid}")
            ->assertOk()
            ->assertJsonPath('candidature.can_rate', true)
            ->assertJsonPath('candidature.my_rating', null);
    }

    public function test_candidature_list_exposes_can_rate_and_my_rating(): void
    {
        $unrated = $this->completedCandidature();
        $rated = $this->completedCandidature();
        $this->rate($rated, 5, null);

        $data = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->json('data');

        $byId = collect($data)->keyBy('id');
        $this->assertTrue($byId[$unrated->uuid]['can_rate']);
        $this->assertNull($byId[$unrated->uuid]['my_rating']);
        $this->assertFalse($byId[$rated->uuid]['can_rate']);
        $this->assertSame(5, $byId[$rated->uuid]['my_rating']['score']);
    }

    public function test_candidature_list_query_count_does_not_grow_with_rated_candidatures(): void
    {
        $this->rate($this->completedCandidature());

        DB::enableQueryLog();
        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::flushQueryLog();

        foreach (range(1, 4) as $i) {
            $this->rate($this->completedCandidature());
        }
        $this->completedCandidature();
        DB::flushQueryLog();

        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($baseline, $count, 'N+1 sur my_rating / can_rate');
    }

    public function test_rating_through_the_ui_flow_then_second_attempt_is_already_rated(): void
    {
        $candidature = $this->completedCandidature();
        $missionUuid = $candidature->mission->uuid;

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$missionUuid}")
            ->assertJsonPath('candidature.can_rate', true);

        $this->postJson("/api/v1/face/candidatures/{$candidature->uuid}/rate", ['score' => 5, 'comment' => 'Top'])
            ->assertCreated();

        $this->getJson("/api/v1/face/missions/{$missionUuid}")
            ->assertJsonPath('candidature.can_rate', false)
            ->assertJsonPath('candidature.my_rating.score', 5);

        $this->postJson("/api/v1/face/candidatures/{$candidature->uuid}/rate", ['score' => 1])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_RATED');
    }

    public function test_mission_completed_mail_links_to_the_mission_page_for_rating(): void
    {
        $mission = Mission::factory()->create();
        $face = Face::factory()->create();

        $rendered = (new MissionCompletedMail($face, $mission, 90000))->render();

        $this->assertStringContainsString("/face/missions/{$mission->uuid}", $rendered);
        $this->assertStringContainsString('/face/wallet', $rendered);
        $this->assertStringContainsString('noter le producteur', $rendered);
    }
}
