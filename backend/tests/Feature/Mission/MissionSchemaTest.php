<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MissionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_missions_table_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('missions'));

        $expectedColumns = [
            'id',
            'producer_id',
            'titre',
            'description',
            'date_tournage',
            'profil_recherche',
            'budget',
            'date_limite_candidature',
            'nombre_faces_voulu',
            'type_mission',
            'type_mission_autre',
            'genre_voulu',
            'lieu',
            'duree',
            'status',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('missions', $column),
                "Column '{$column}' should exist in missions table"
            );
        }
    }

    public function test_mission_has_foreign_key_to_producer(): void
    {
        $producer = Producer::factory()->create();

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
        ]);

        $this->assertEquals($producer->id, $mission->producer_id);
        $this->assertInstanceOf(Producer::class, $mission->producer);
    }

    public function test_foreign_key_constraint_deletes_missions_when_producer_deleted(): void
    {
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
        ]);

        $missionId = $mission->id;

        $producer->delete();

        $this->assertDatabaseMissing('missions', ['id' => $missionId]);
    }

    public function test_mission_status_defaults_to_draft(): void
    {
        $producer = Producer::factory()->create();

        $mission = Mission::create([
            'producer_id' => $producer->id,
            'titre' => 'Test Mission',
            'description' => 'Test description',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Recherche profile test',
            'budget' => 100000,
            'date_limite_candidature' => now()->addWeek(),
            'nombre_faces_voulu' => 2,
            'type_mission' => MissionType::Publicite,
            'genre_voulu' => MissionGender::Tous,
            'lieu' => 'Cotonou, Bénin',
            'duree' => '2 jours',
        ]);

        $this->assertEquals(MissionStatus::Draft, $mission->status);
    }

    public function test_mission_status_defaults_to_draft_in_database(): void
    {
        $producer = Producer::factory()->create();

        // Insert directly without specifying status
        $missionId = \DB::table('missions')->insertGetId([
            'producer_id' => $producer->id,
            'titre' => 'Direct Insert Test',
            'slug' => 'direct-insert-test',
            'description' => 'Test description',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Test profile',
            'budget' => 50000,
            'date_limite_candidature' => now()->addWeek(),
            'nombre_faces_voulu' => 1,
            'type_mission' => 'publicite',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => '1 jour',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('missions', [
            'id' => $missionId,
            'status' => 'draft',
        ]);
    }

    public function test_producer_has_many_missions_relationship(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->count(3)->create([
            'producer_id' => $producer->id,
        ]);

        $this->assertCount(3, $producer->missions);
        $this->assertInstanceOf(Mission::class, $producer->missions->first());
    }

    public function test_mission_belongs_to_producer_relationship(): void
    {
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
        ]);

        $this->assertInstanceOf(Producer::class, $mission->producer);
        $this->assertEquals($producer->id, $mission->producer->id);
    }

    public function test_mission_status_enum_casting_works(): void
    {
        $mission = Mission::factory()->create([
            'status' => MissionStatus::Published,
        ]);

        $mission->refresh();

        $this->assertInstanceOf(MissionStatus::class, $mission->status);
        $this->assertEquals(MissionStatus::Published, $mission->status);
    }

    public function test_mission_type_enum_casting_works(): void
    {
        $mission = Mission::factory()->create([
            'type_mission' => MissionType::Film,
        ]);

        $mission->refresh();

        $this->assertInstanceOf(MissionType::class, $mission->type_mission);
        $this->assertEquals(MissionType::Film, $mission->type_mission);
    }

    public function test_mission_gender_enum_casting_works(): void
    {
        $mission = Mission::factory()->create([
            'genre_voulu' => MissionGender::Femme,
        ]);

        $mission->refresh();

        $this->assertInstanceOf(MissionGender::class, $mission->genre_voulu);
        $this->assertEquals(MissionGender::Femme, $mission->genre_voulu);
    }

    public function test_mission_date_fields_are_cast_correctly(): void
    {
        $dateTournage = now()->addMonth();
        $dateLimite = now()->addWeek();

        $mission = Mission::factory()->create([
            'date_tournage' => $dateTournage,
            'date_limite_candidature' => $dateLimite,
        ]);

        $mission->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $mission->date_tournage);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $mission->date_limite_candidature);
    }

    public function test_mission_status_scope_filters_correctly(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->draft()->create(['producer_id' => $producer->id]);
        Mission::factory()->published()->create(['producer_id' => $producer->id]);
        Mission::factory()->published()->create(['producer_id' => $producer->id]);
        Mission::factory()->closed()->create(['producer_id' => $producer->id]);
        Mission::factory()->completed()->create(['producer_id' => $producer->id]);

        $this->assertCount(1, Mission::draft()->get());
        $this->assertCount(2, Mission::published()->get());
        $this->assertCount(1, Mission::closed()->get());
        $this->assertCount(1, Mission::completed()->get());
    }

    public function test_mission_status_enum_has_correct_labels(): void
    {
        $this->assertEquals('Brouillon', MissionStatus::Draft->label());
        $this->assertEquals('Publiée', MissionStatus::Published->label());
        $this->assertEquals('Clôturée', MissionStatus::Closed->label());
        $this->assertEquals('Terminée', MissionStatus::Completed->label());
    }

    public function test_mission_type_enum_has_correct_labels(): void
    {
        $this->assertEquals('Publicité', MissionType::Publicite->label());
        $this->assertEquals('Film', MissionType::Film->label());
        $this->assertEquals('Court-métrage', MissionType::CourtMetrage->label());
        $this->assertEquals('Clip musical', MissionType::ClipMusical->label());
        $this->assertEquals('Autre', MissionType::Autre->label());
    }

    public function test_mission_gender_enum_has_correct_labels(): void
    {
        $this->assertEquals('Homme', MissionGender::Homme->label());
        $this->assertEquals('Femme', MissionGender::Femme->label());
        $this->assertEquals('Homme et Femme', MissionGender::Tous->label());
    }

    public function test_mission_budget_is_cast_to_integer(): void
    {
        $mission = Mission::factory()->create([
            'budget' => 150000,
        ]);

        $mission->refresh();

        $this->assertIsInt($mission->budget);
        $this->assertEquals(150000, $mission->budget);
    }

    public function test_mission_nombre_faces_voulu_is_cast_to_integer(): void
    {
        $mission = Mission::factory()->create([
            'nombre_faces_voulu' => 5,
        ]);

        $mission->refresh();

        $this->assertIsInt($mission->nombre_faces_voulu);
        $this->assertEquals(5, $mission->nombre_faces_voulu);
    }

    public function test_mission_factory_creates_valid_mission(): void
    {
        $mission = Mission::factory()->create();

        $this->assertNotNull($mission->id);
        $this->assertNotNull($mission->producer_id);
        $this->assertNotNull($mission->titre);
        $this->assertNotNull($mission->description);
        $this->assertNotNull($mission->date_tournage);
        $this->assertNotNull($mission->profil_recherche);
        $this->assertNotNull($mission->budget);
        $this->assertNotNull($mission->date_limite_candidature);
        $this->assertNotNull($mission->nombre_faces_voulu);
        $this->assertNotNull($mission->type_mission);
        $this->assertNotNull($mission->genre_voulu);
        $this->assertNotNull($mission->lieu);
        $this->assertNotNull($mission->duree);
        $this->assertNotNull($mission->status);
    }

    public function test_accepting_candidatures_scope_filters_correctly(): void
    {
        $producer = Producer::factory()->create();

        // Published with future deadline - should be included
        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'date_limite_candidature' => now()->addWeek(),
        ]);

        // Published with past deadline - should be excluded
        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'date_limite_candidature' => now()->subDay(),
        ]);

        // Draft - should be excluded
        Mission::factory()->draft()->create([
            'producer_id' => $producer->id,
            'date_limite_candidature' => now()->addWeek(),
        ]);

        $this->assertCount(1, Mission::acceptingCandidatures()->get());
    }

    public function test_enum_values_helper_returns_string_array(): void
    {
        $statusValues = MissionStatus::values();
        $typeValues = MissionType::values();
        $genderValues = MissionGender::values();

        $this->assertEquals(['draft', 'published', 'pending_payment', 'closed', 'completed'], $statusValues);
        $this->assertEquals(['publicite', 'film', 'court_metrage', 'clip_musical', 'shooting_photo', 'autre'], $typeValues);
        $this->assertEquals(['homme', 'femme', 'tous'], $genderValues);
    }

    public function test_type_mission_autre_is_nullable_and_stored_correctly(): void
    {
        // Without type_mission_autre (null)
        $mission = Mission::factory()->create([
            'type_mission' => MissionType::Film,
            'type_mission_autre' => null,
        ]);

        $mission->refresh();
        $this->assertNull($mission->type_mission_autre);

        // With type_mission_autre
        $missionAutre = Mission::factory()->autre()->create();
        $missionAutre->refresh();

        $this->assertEquals(MissionType::Autre, $missionAutre->type_mission);
        $this->assertNotNull($missionAutre->type_mission_autre);
        $this->assertIsString($missionAutre->type_mission_autre);
    }

    public function test_type_mission_autre_is_required_when_type_is_autre(): void
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/producer/missions', [
            'titre' => 'Mission test autre',
            'description' => 'Description test',
            'date_tournage' => now()->addMonth()->toDateString(),
            'profil_recherche' => 'Profil test',
            'budget' => 100000,
            'date_limite_candidature' => now()->addWeek()->toDateString(),
            'nombre_faces_voulu' => 1,
            'type_mission' => 'autre',
            'type_mission_autre' => '', // empty — should fail
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => '2 jours',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('type_mission_autre');
    }

    public function test_type_mission_autre_is_nullified_when_type_is_not_autre(): void
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/producer/missions', [
            'titre' => 'Mission film test',
            'description' => 'Description test',
            'date_tournage' => now()->addMonth()->toDateString(),
            'profil_recherche' => 'Profil test',
            'budget' => 100000,
            'date_limite_candidature' => now()->addWeek()->toDateString(),
            'nombre_faces_voulu' => 1,
            'type_mission' => 'film',
            'type_mission_autre' => 'should be nullified',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => '2 jours',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('missions', [
            'titre' => 'Mission film test',
            'type_mission' => 'film',
            'type_mission_autre' => null,
        ]);
    }
}
