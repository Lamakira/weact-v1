<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\ProducerType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_missions_count_returns_zero_when_no_missions(): void
    {
        $producer = Producer::factory()->create();

        $this->assertEquals(0, $producer->published_missions_count);
    }

    public function test_published_missions_count_returns_correct_count_for_published_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->count(3)->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $this->assertEquals(3, $producer->published_missions_count);
    }

    public function test_published_missions_count_excludes_draft_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Draft,
        ]);

        $this->assertEquals(0, $producer->published_missions_count);
    }

    public function test_published_missions_count_excludes_closed_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Closed,
        ]);

        $this->assertEquals(0, $producer->published_missions_count);
    }

    public function test_published_missions_count_excludes_completed_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $this->assertEquals(0, $producer->published_missions_count);
    }

    public function test_published_missions_count_only_counts_published_in_mixed_statuses(): void
    {
        $producer = Producer::factory()->create();

        // 2 published
        Mission::factory()->count(2)->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        // 1 draft
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Draft,
        ]);

        // 1 closed
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Closed,
        ]);

        // 1 completed
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $this->assertEquals(2, $producer->published_missions_count);
    }

    public function test_published_missions_count_does_not_count_other_producers_missions(): void
    {
        $producer1 = Producer::factory()->create();
        $producer2 = Producer::factory()->create();

        // Producer 1 has 2 published missions
        Mission::factory()->count(2)->create([
            'producer_id' => $producer1->id,
            'status' => MissionStatus::Published,
        ]);

        // Producer 2 has 3 published missions
        Mission::factory()->count(3)->create([
            'producer_id' => $producer2->id,
            'status' => MissionStatus::Published,
        ]);

        $this->assertEquals(2, $producer1->published_missions_count);
        $this->assertEquals(3, $producer2->published_missions_count);
    }

    public function test_in_progress_missions_count_excludes_ugc_missions(): void
    {
        // UGC 2.4 : une acceptation UGC pose la candidature directement `confirmed` —
        // le compteur public (PublicProducerResource) ne doit pas la refléter (FR5/2.1 :
        // l'UGC est invisible de toutes les surfaces publiques, parité publishedMissionsCount).
        $producer = Producer::factory()->create();

        $cashMission = Mission::factory()->published()->create(['producer_id' => $producer->id]);
        $ugcMission = Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'type_mission' => 'ugc',
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'commission_paid_at' => now(),
        ]);

        Candidature::factory()->create([
            'mission_id' => $cashMission->id,
            'face_id' => Face::factory()->create()->id,
            'status' => CandidatureStatus::Confirmed,
        ]);
        Candidature::factory()->create([
            'mission_id' => $ugcMission->id,
            'face_id' => Face::factory()->create()->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $this->assertEquals(1, $producer->in_progress_missions_count);
    }

    public function test_it_generates_a_slug_from_the_producer_identity(): void
    {
        $producer = Producer::factory()->create([
            'slug' => null,
            'type' => ProducerType::Agency,
            'agency_name' => 'Studio XYZ',
        ]);

        $this->assertSame('studio-xyz', $producer->slug);
    }

    public function test_it_regenerates_the_slug_when_identity_fields_change(): void
    {
        $producer = Producer::factory()->create([
            'slug' => null,
            'type' => ProducerType::Agency,
            'agency_name' => 'Studio XYZ',
        ]);

        $producer->update(['agency_name' => 'Studio Alpha']);

        $this->assertSame('studio-alpha', $producer->fresh()->slug);
    }

    public function test_it_falls_back_to_a_default_slug_when_identity_is_blank(): void
    {
        $producer = Producer::factory()->create([
            'slug' => null,
            'type' => ProducerType::Particulier,
            'first_name' => '',
            'last_name' => '',
        ]);

        $this->assertSame('producer', $producer->slug);
    }
}
