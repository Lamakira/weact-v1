<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CandidatureSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidatures_table_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('candidatures'));

        $expectedColumns = [
            'id',
            'face_id',
            'mission_id',
            'message_motivation',
            'status',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('candidatures', $column),
                "Column '{$column}' should exist in candidatures table"
            );
        }
    }

    public function test_candidature_has_foreign_key_to_face(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $this->assertEquals($face->id, $candidature->face_id);
        $this->assertInstanceOf(Face::class, $candidature->face);
    }

    public function test_candidature_has_foreign_key_to_mission(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $this->assertEquals($mission->id, $candidature->mission_id);
        $this->assertInstanceOf(Mission::class, $candidature->mission);
    }

    public function test_foreign_key_constraint_deletes_candidature_when_face_deleted(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $candidatureId = $candidature->id;

        $face->delete();

        $this->assertDatabaseMissing('candidatures', ['id' => $candidatureId]);
    }

    public function test_foreign_key_constraint_deletes_candidature_when_mission_deleted(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $candidatureId = $candidature->id;

        $mission->delete();

        $this->assertDatabaseMissing('candidatures', ['id' => $candidatureId]);
    }

    public function test_candidature_status_defaults_to_pending(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        $candidature = Candidature::create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $this->assertEquals(CandidatureStatus::Pending, $candidature->status);
    }

    public function test_candidature_status_defaults_to_pending_in_database(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        // Insert directly without specifying status
        $candidatureId = \DB::table('candidatures')->insertGetId([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('candidatures', [
            'id' => $candidatureId,
            'status' => 'pending',
        ]);
    }

    public function test_unique_constraint_prevents_duplicate_face_mission_pairs(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $this->expectException(QueryException::class);

        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);
    }

    public function test_same_face_can_apply_to_different_missions(): void
    {
        $face = Face::factory()->create();
        $mission1 = Mission::factory()->published()->create();
        $mission2 = Mission::factory()->published()->create();

        $candidature1 = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission1->id,
        ]);

        $candidature2 = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission2->id,
        ]);

        $this->assertDatabaseHas('candidatures', ['id' => $candidature1->id]);
        $this->assertDatabaseHas('candidatures', ['id' => $candidature2->id]);
    }

    public function test_different_faces_can_apply_to_same_mission(): void
    {
        $face1 = Face::factory()->create();
        $face2 = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        $candidature1 = Candidature::factory()->create([
            'face_id' => $face1->id,
            'mission_id' => $mission->id,
        ]);

        $candidature2 = Candidature::factory()->create([
            'face_id' => $face2->id,
            'mission_id' => $mission->id,
        ]);

        $this->assertDatabaseHas('candidatures', ['id' => $candidature1->id]);
        $this->assertDatabaseHas('candidatures', ['id' => $candidature2->id]);
    }

    public function test_face_has_many_candidatures_relationship(): void
    {
        $face = Face::factory()->create();

        Candidature::factory()->count(3)->create([
            'face_id' => $face->id,
        ]);

        $this->assertCount(3, $face->candidatures);
        $this->assertInstanceOf(Candidature::class, $face->candidatures->first());
    }

    public function test_mission_has_many_candidatures_relationship(): void
    {
        $mission = Mission::factory()->published()->create();

        Candidature::factory()->count(3)->create([
            'mission_id' => $mission->id,
        ]);

        $this->assertCount(3, $mission->candidatures);
        $this->assertInstanceOf(Candidature::class, $mission->candidatures->first());
    }

    public function test_candidature_belongs_to_face_relationship(): void
    {
        $face = Face::factory()->create();
        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
        ]);

        $this->assertInstanceOf(Face::class, $candidature->face);
        $this->assertEquals($face->id, $candidature->face->id);
    }

    public function test_candidature_belongs_to_mission_relationship(): void
    {
        $mission = Mission::factory()->published()->create();
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
        ]);

        $this->assertInstanceOf(Mission::class, $candidature->mission);
        $this->assertEquals($mission->id, $candidature->mission->id);
    }

    public function test_candidature_status_enum_casting_works(): void
    {
        $candidature = Candidature::factory()->create([
            'status' => CandidatureStatus::Accepted,
        ]);

        $candidature->refresh();

        $this->assertInstanceOf(CandidatureStatus::class, $candidature->status);
        $this->assertEquals(CandidatureStatus::Accepted, $candidature->status);
    }

    public function test_candidature_status_scope_pending_filters_correctly(): void
    {
        Candidature::factory()->pending()->count(2)->create();
        Candidature::factory()->accepted()->count(1)->create();
        Candidature::factory()->rejected()->count(1)->create();

        $this->assertCount(2, Candidature::pending()->get());
    }

    public function test_candidature_status_scope_accepted_filters_correctly(): void
    {
        Candidature::factory()->pending()->count(2)->create();
        Candidature::factory()->accepted()->count(3)->create();
        Candidature::factory()->rejected()->count(1)->create();

        $this->assertCount(3, Candidature::accepted()->get());
    }

    public function test_candidature_status_scope_confirmed_filters_correctly(): void
    {
        Candidature::factory()->accepted()->count(2)->create();
        Candidature::factory()->confirmed()->count(2)->create();
        Candidature::factory()->inProgress()->count(1)->create();

        $this->assertCount(2, Candidature::confirmed()->get());
    }

    public function test_candidature_status_scope_in_progress_filters_correctly(): void
    {
        Candidature::factory()->confirmed()->count(2)->create();
        Candidature::factory()->inProgress()->count(3)->create();
        Candidature::factory()->completed()->count(1)->create();

        $this->assertCount(3, Candidature::inProgress()->get());
    }

    public function test_candidature_status_scope_completed_filters_correctly(): void
    {
        Candidature::factory()->inProgress()->count(2)->create();
        Candidature::factory()->completed()->count(4)->create();
        Candidature::factory()->rejected()->count(1)->create();

        $this->assertCount(4, Candidature::completed()->get());
    }

    public function test_candidature_status_scope_rejected_filters_correctly(): void
    {
        Candidature::factory()->pending()->count(2)->create();
        Candidature::factory()->accepted()->count(1)->create();
        Candidature::factory()->rejected()->count(3)->create();

        $this->assertCount(3, Candidature::rejected()->get());
    }

    public function test_candidature_status_enum_has_correct_labels(): void
    {
        $this->assertEquals('En attente', CandidatureStatus::Pending->label());
        $this->assertEquals('Acceptée', CandidatureStatus::Accepted->label());
        $this->assertEquals('Confirmée', CandidatureStatus::Confirmed->label());
        $this->assertEquals('En cours', CandidatureStatus::InProgress->label());
        $this->assertEquals('Terminée', CandidatureStatus::Completed->label());
        $this->assertEquals('Refusée', CandidatureStatus::Rejected->label());
        $this->assertEquals('Annulée', CandidatureStatus::Cancelled->label());
    }

    public function test_candidature_status_enum_values_helper_returns_string_array(): void
    {
        $values = CandidatureStatus::values();

        $this->assertEquals([
            'pending',
            'accepted',
            'confirmed',
            'in_progress',
            'completed',
            'rejected',
            'cancelled',
        ], $values);
    }

    public function test_candidature_message_motivation_is_nullable(): void
    {
        $candidature = Candidature::factory()->withoutMotivation()->create();

        $this->assertNull($candidature->message_motivation);
        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'message_motivation' => null,
        ]);
    }

    public function test_candidature_can_have_motivation_message(): void
    {
        $candidature = Candidature::factory()->withMotivation()->create();

        $this->assertNotNull($candidature->message_motivation);
        $this->assertIsString($candidature->message_motivation);
    }

    public function test_candidature_factory_creates_valid_candidature(): void
    {
        $candidature = Candidature::factory()->create();

        $this->assertNotNull($candidature->id);
        $this->assertNotNull($candidature->face_id);
        $this->assertNotNull($candidature->mission_id);
        $this->assertNotNull($candidature->status);
        $this->assertInstanceOf(CandidatureStatus::class, $candidature->status);
    }

    public function test_candidature_factory_states_work_correctly(): void
    {
        $pending = Candidature::factory()->pending()->create();
        $accepted = Candidature::factory()->accepted()->create();
        $confirmed = Candidature::factory()->confirmed()->create();
        $inProgress = Candidature::factory()->inProgress()->create();
        $completed = Candidature::factory()->completed()->create();
        $rejected = Candidature::factory()->rejected()->create();

        $this->assertEquals(CandidatureStatus::Pending, $pending->status);
        $this->assertEquals(CandidatureStatus::Accepted, $accepted->status);
        $this->assertEquals(CandidatureStatus::Confirmed, $confirmed->status);
        $this->assertEquals(CandidatureStatus::InProgress, $inProgress->status);
        $this->assertEquals(CandidatureStatus::Completed, $completed->status);
        $this->assertEquals(CandidatureStatus::Rejected, $rejected->status);
    }

    public function test_candidatures_can_be_eager_loaded_with_face_and_mission(): void
    {
        Candidature::factory()->count(3)->create();

        $candidatures = Candidature::with(['face', 'mission'])->get();

        $this->assertCount(3, $candidatures);
        $this->assertInstanceOf(Face::class, $candidatures->first()->face);
        $this->assertInstanceOf(Mission::class, $candidatures->first()->mission);
    }

    public function test_candidature_status_allows_chat_access_helper(): void
    {
        // Chat should NOT be allowed for pending or rejected
        $this->assertFalse(CandidatureStatus::Pending->allowsChatAccess());
        $this->assertFalse(CandidatureStatus::Rejected->allowsChatAccess());

        // Chat should be allowed for accepted and beyond
        $this->assertTrue(CandidatureStatus::Accepted->allowsChatAccess());
        $this->assertTrue(CandidatureStatus::Confirmed->allowsChatAccess());
        $this->assertTrue(CandidatureStatus::InProgress->allowsChatAccess());
        $this->assertTrue(CandidatureStatus::Completed->allowsChatAccess());
    }

    public function test_candidature_status_allows_ratings_helper(): void
    {
        // Ratings should ONLY be allowed for completed
        $this->assertFalse(CandidatureStatus::Pending->allowsRatings());
        $this->assertFalse(CandidatureStatus::Accepted->allowsRatings());
        $this->assertFalse(CandidatureStatus::Confirmed->allowsRatings());
        $this->assertFalse(CandidatureStatus::InProgress->allowsRatings());
        $this->assertFalse(CandidatureStatus::Rejected->allowsRatings());

        $this->assertTrue(CandidatureStatus::Completed->allowsRatings());
    }
}
