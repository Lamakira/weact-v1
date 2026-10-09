<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProducerActiveMissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    private function url(string $query = ''): string
    {
        return '/api/v1/producer/dashboard/active-missions'.$query;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_forbidden_for_a_face(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $this->actingAs($faceUser)->getJson($this->url())->assertForbidden();
    }

    public function test_returns_empty_list_with_zero_total(): void
    {
        $this->actingAs($this->producerUser)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_lists_published_and_in_progress_missions_only(): void
    {
        $published = Mission::factory()->published()->create(['producer_id' => $this->producer->id, 'nombre_faces_voulu' => 3]);
        $inProgress = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        Candidature::factory()->confirmed()->create(['mission_id' => $inProgress->id]);
        // Exclus : brouillon, clôturée sans travail en cours, terminée
        Mission::factory()->draft()->create(['producer_id' => $this->producer->id]);
        Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);

        $response = $this->actingAs($this->producerUser)->getJson($this->url());

        $response->assertOk()->assertJsonPath('meta.total', 2);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$published->uuid, $inProgress->uuid], $ids);
    }

    public function test_exposes_counts_and_faces_wanted(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id, 'nombre_faces_voulu' => 3]);
        Candidature::factory()->pending()->create(['mission_id' => $mission->id]);
        Candidature::factory()->confirmed()->create(['mission_id' => $mission->id]);
        Candidature::factory()->inProgress()->create(['mission_id' => $mission->id]);
        $old = Candidature::factory()->pending()->create(['mission_id' => $mission->id]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();

        $row = $this->actingAs($this->producerUser)->getJson($this->url())
            ->assertOk()->json('data.0');

        $this->assertSame($mission->uuid, $row['id']);
        $this->assertSame($mission->titre, $row['titre']);
        $this->assertSame('published', $row['status']);
        $this->assertSame(4, $row['candidatures_count']);
        $this->assertSame(3, $row['new_candidatures_count']);
        $this->assertSame(2, $row['confirmed_count']);
        $this->assertSame(3, $row['faces_wanted']);
    }

    public function test_respects_limit_but_total_counts_all(): void
    {
        Mission::factory()->published()->count(4)->create(['producer_id' => $this->producer->id]);

        $this->actingAs($this->producerUser)->getJson($this->url('?limit=2'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4);
    }

    public function test_rejects_invalid_limit(): void
    {
        $this->actingAs($this->producerUser)->getJson($this->url('?limit=500'))
            ->assertStatus(422);
    }

    public function test_only_own_missions(): void
    {
        $other = Producer::factory()->create();
        Mission::factory()->published()->create(['producer_id' => $other->id]);
        $mine = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        $response = $this->actingAs($this->producerUser)->getJson($this->url())->assertOk();

        $this->assertSame([$mine->uuid], collect($response->json('data'))->pluck('id')->all());
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_query_count_is_constant(): void
    {
        $this->actingAs($this->producerUser)->getJson($this->url())->assertOk();

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->url())->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $missions = Mission::factory()->published()->count(2)->create(['producer_id' => $this->producer->id]);
        Candidature::factory()->pending()->create(['mission_id' => $missions[0]->id]);
        $small = $count();

        foreach (Mission::factory()->published()->count(4)->create(['producer_id' => $this->producer->id]) as $m) {
            Candidature::factory()->confirmed()->count(2)->create(['mission_id' => $m->id]);
        }
        $large = $count();

        $this->assertSame($small, $large);
    }
}
