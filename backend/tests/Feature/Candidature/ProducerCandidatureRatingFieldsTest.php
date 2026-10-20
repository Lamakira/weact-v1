<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
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
 * Lot C8 : `can_rate` et `my_rating` sur GET /producer/missions/{mission}/candidatures,
 * pour le Producteur authentifié uniquement.
 */
class ProducerCandidatureRatingFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Completed,
        ]);
    }

    /** @return array{0: Candidature, 1: User} */
    private function candidature(CandidatureStatus $status): array
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $face->id,
            'status' => $status,
        ]);

        return [$candidature, $faceUser];
    }

    private function producerRates(Candidature $candidature, int $score, ?string $comment = null): void
    {
        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $candidature->face_id,
            'rated_type' => Face::class,
            'score' => $score,
            'comment' => $comment,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return collect(
            $this->actingAs($this->producerUser)
                ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures")
                ->assertOk()
                ->json('data'),
        )->keyBy('id')->all();
    }

    public function test_completed_candidature_without_rating_can_be_rated(): void
    {
        [$candidature] = $this->candidature(CandidatureStatus::Completed);

        $row = $this->rows()[$candidature->uuid];

        $this->assertTrue($row['can_rate']);
        $this->assertNull($row['my_rating']);
    }

    public function test_rated_candidature_exposes_my_rating_and_cannot_be_rated_again(): void
    {
        [$candidature] = $this->candidature(CandidatureStatus::Completed);
        $this->producerRates($candidature, 4, 'Très pro');

        $row = $this->rows()[$candidature->uuid];

        $this->assertFalse($row['can_rate']);
        $this->assertSame(4, $row['my_rating']['score']);
        $this->assertSame('Très pro', $row['my_rating']['comment']);
        $this->assertNotNull($row['my_rating']['created_at']);
    }

    public function test_non_completed_candidatures_cannot_be_rated(): void
    {
        [$confirmed] = $this->candidature(CandidatureStatus::Confirmed);
        [$pending] = $this->candidature(CandidatureStatus::Pending);

        $rows = $this->rows();

        foreach ([$confirmed, $pending] as $candidature) {
            $this->assertFalse($rows[$candidature->uuid]['can_rate']);
            $this->assertNull($rows[$candidature->uuid]['my_rating']);
        }
    }

    public function test_the_faces_own_rating_of_the_producer_is_never_exposed_as_my_rating(): void
    {
        [$candidature, $faceUser] = $this->candidature(CandidatureStatus::Completed);
        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 2,
            'comment' => 'Avis de la Face',
        ]);

        $row = $this->rows()[$candidature->uuid];

        $this->assertTrue($row['can_rate']);
        $this->assertNull($row['my_rating']);
    }

    public function test_rate_fields_follow_the_post_rate_endpoint(): void
    {
        [$candidature] = $this->candidature(CandidatureStatus::Completed);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/rate", ['score' => 5])
            ->assertCreated();

        $row = $this->rows()[$candidature->uuid];

        $this->assertFalse($row['can_rate']);
        $this->assertSame(5, $row['my_rating']['score']);
        $this->assertNull($row['my_rating']['comment']);
    }

    public function test_rating_fields_add_no_per_row_query(): void
    {
        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->producerUser)
                ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/candidatures")
                ->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        foreach (range(1, 2) as $i) {
            [$candidature] = $this->candidature(CandidatureStatus::Completed);
            $this->producerRates($candidature, 3);
        }
        $small = $measure();

        foreach (range(1, 6) as $i) {
            [$candidature] = $this->candidature(CandidatureStatus::Completed);
            if ($i % 2 === 0) {
                $this->producerRates($candidature, 5);
            }
        }
        $large = $measure();

        $this->assertSame($small, $large, 'Le nombre de requêtes ne doit pas croître avec le nombre de candidatures.');
    }
}
